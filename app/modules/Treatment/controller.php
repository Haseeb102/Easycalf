<?php
class TreatmentController {
    private $db;
    private $auth;

    public function __construct() {
        $this->db = new Database();
        $this->auth = new Auth();
    }

    public function list() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        $treatments = $this->db->fetchAll("
            SELECT 
                tp.*,
                c.calf_id,
                c.health_status,
                b.name as batch_name,
                b.location,
                u.name as created_by_name,
                DATEDIFF(NOW(), tp.start_date) as days_elapsed
            FROM treatment_plans tp
            JOIN calves c ON tp.calf_id = c.id
            LEFT JOIN batches b ON c.batch_id = b.id
            JOIN users u ON tp.created_by = u.id
            WHERE tp.status = 'active'
            ORDER BY tp.start_date DESC, c.calf_id ASC
        ");

        $this->renderTreatmentDashboard($treatments);
    }

    public function quickElectrolyte() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calf_id'])) {
            try {
                $calfId = $_POST['calf_id'];
                
                $calf = $this->db->fetch("SELECT id, calf_id FROM calves WHERE calf_id = ? AND status = 'active'", [$calfId]);
                if (!$calf) {
                    throw new Exception("Calf '$calfId' not found or not active");
                }

                $existingTreatment = $this->db->fetch("
                    SELECT id FROM treatment_plans 
                    WHERE calf_id = ? 
                    AND treatment_type = 'electrolyte' 
                    AND status = 'active'",
                    [$calf['id']]
                );

                if ($existingTreatment) {
                    throw new Exception("This calf already has an active electrolyte treatment");
                }

                $this->db->query("
                    INSERT INTO treatment_plans (calf_id, treatment_type, treatment_name, is_custom, start_date, duration_days, current_day, status, notes, created_by, created_at)
                    VALUES (?, 'electrolyte', 'Electrolyte Solution', 0, CURDATE(), 3, 1, 'active', 'Quick electrolyte treatment (3 days)', ?, NOW())",
                    [$calf['id'], $_SESSION['user_id']]
                );

                $treatmentPlanId = $this->db->lastInsertId();
                $eventId = $this->getOrCreateTreatmentEvent('electrolyte', 'Electrolyte Solution');

                for ($day = 0; $day < 3; $day++) {
                    $dueDate = date('Y-m-d', strtotime("+$day days"));
                    $this->db->query("
                        INSERT INTO calf_events (calf_id, event_id, due_date, status, created_at)
                        VALUES (?, ?, ?, 'pending', NOW())",
                        [$calf['id'], $eventId, $dueDate]
                    );
                }

                $this->logActivity("Started 3-day electrolyte treatment for calf " . $calf['calf_id'], $calf['id'], $treatmentPlanId);
                $_SESSION['success_message'] = "✅ 3-day electrolyte treatment started for calf " . $calf['calf_id'];

            } catch (Exception $e) {
                $_SESSION['error_message'] = "❌ Error: " . $e->getMessage();
            }
        }

        header('Location: /public/calves');
        exit;
    }

    public function cancelElectrolyte() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calf_id'])) {
            try {
                $calfId = $_POST['calf_id'];
                
                $calf = $this->db->fetch("SELECT id FROM calves WHERE calf_id = ? AND status = 'active'", [$calfId]);
                if (!$calf) {
                    throw new Exception("Calf not found");
                }

                $treatment = $this->db->fetch("
                    SELECT id FROM treatment_plans 
                    WHERE calf_id = ? 
                    AND treatment_type = 'electrolyte' 
                    AND status = 'active'",
                    [$calf['id']]
                );

                if (!$treatment) {
                    throw new Exception("No active electrolyte treatment found");
                }

                $this->db->query("
                    UPDATE treatment_plans 
                    SET status = 'cancelled', 
                        notes = CONCAT(notes, '\nCancelled by user'), 
                        updated_at = NOW() 
                    WHERE id = ?",
                    [$treatment['id']]
                );

                $this->db->query("
                    UPDATE calf_events 
                    SET status = 'cancelled' 
                    WHERE calf_id = ? 
                    AND due_date >= CURDATE() 
                    AND status = 'pending'",
                    [$calf['id']]
                );

                $this->logActivity("Cancelled electrolyte treatment for calf $calfId", $calf['id'], $treatment['id']);
                $_SESSION['success_message'] = "✅ Electrolyte treatment cancelled for calf $calfId";

            } catch (Exception $e) {
                $_SESSION['error_message'] = "❌ Error: " . $e->getMessage();
            }
        }

        header('Location: /public/calves');
        exit;
    }

    public function bulkElectrolyte() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calf_ids'])) {
            try {
                $calfIds = $_POST['calf_ids'];
                $durationDays = intval($_POST['duration_days'] ?? 3);
                
                if (empty($calfIds) || !is_array($calfIds)) {
                    throw new Exception("No calves selected");
                }

                if ($durationDays < 1 || $durationDays > 30) {
                    throw new Exception("Duration must be between 1 and 30 days");
                }

                $count = 0;
                $eventId = $this->getOrCreateTreatmentEvent('electrolyte', 'Electrolyte Solution');

                foreach ($calfIds as $calfId) {
                    $calf = $this->db->fetch("SELECT id FROM calves WHERE id = ? AND status = 'active'", [$calfId]);
                    if (!$calf) continue;

                    $existingTreatment = $this->db->fetch("
                        SELECT id FROM treatment_plans 
                        WHERE calf_id = ? 
                        AND treatment_type = 'electrolyte' 
                        AND status = 'active'",
                        [$calfId]
                    );

                    if ($existingTreatment) continue;

                    $this->db->query("
                        INSERT INTO treatment_plans (calf_id, treatment_type, treatment_name, is_custom, start_date, duration_days, current_day, status, notes, created_by, created_at)
                        VALUES (?, 'electrolyte', 'Electrolyte Solution', 0, CURDATE(), ?, 1, 'active', 'Bulk electrolyte treatment', ?, NOW())",
                        [$calfId, $durationDays, $_SESSION['user_id']]
                    );

                    for ($day = 0; $day < $durationDays; $day++) {
                        $dueDate = date('Y-m-d', strtotime("+$day days"));
                        $this->db->query("
                            INSERT INTO calf_events (calf_id, event_id, due_date, status, created_at)
                            VALUES (?, ?, ?, 'pending', NOW())",
                            [$calfId, $eventId, $dueDate]
                        );
                    }

                    $count++;
                }

                $_SESSION['success_message'] = "✅ $count calves started on $durationDays-day electrolyte treatment";

            } catch (Exception $e) {
                $_SESSION['error_message'] = "❌ Error: " . $e->getMessage();
            }
        }

        header('Location: /public/calves');
        exit;
    }

    public function completeDay() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['treatment_plan_id'])) {
            try {
                $treatmentPlanId = intval($_POST['treatment_plan_id']);
                
                $treatment = $this->db->fetch("
                    SELECT tp.*, c.calf_id 
                    FROM treatment_plans tp 
                    JOIN calves c ON tp.calf_id = c.id 
                    WHERE tp.id = ? AND tp.status = 'active'",
                    [$treatmentPlanId]
                );
                
                if (!$treatment) {
                    throw new Exception("Treatment plan not found or not active");
                }
                
                if ($treatment['current_day'] >= $treatment['duration_days']) {
                    $this->db->query("
                        UPDATE treatment_plans 
                        SET status = 'completed', 
                            updated_at = NOW() 
                        WHERE id = ?",
                        [$treatmentPlanId]
                    );
                    
                    $_SESSION['success_message'] = "✅ Treatment completed for calf " . $treatment['calf_id'];
                } else {
                    $this->db->query("
                        INSERT INTO treatment_completions (treatment_plan_id, calf_id, completed_day, completed_by, completed_at, notes)
                        VALUES (?, ?, ?, ?, NOW(), 'Day completed')",
                        [$treatmentPlanId, $treatment['calf_id'], $treatment['current_day'], $_SESSION['user_id']]
                    );
                    
                    $newDay = $treatment['current_day'] + 1;
                    
                    if ($newDay > $treatment['duration_days']) {
                        $this->db->query("
                            UPDATE treatment_plans 
                            SET current_day = ?, 
                                status = 'completed', 
                                updated_at = NOW() 
                            WHERE id = ?",
                            [$newDay, $treatmentPlanId]
                        );
                        
                        $_SESSION['success_message'] = "✅ Day " . $treatment['current_day'] . " completed! Treatment finished for calf " . $treatment['calf_id'];
                    } else {
                        $this->db->query("
                            UPDATE treatment_plans 
                            SET current_day = ?, 
                                updated_at = NOW() 
                            WHERE id = ?",
                            [$newDay, $treatmentPlanId]
                        );
                        
                        $_SESSION['success_message'] = "✅ Day " . $treatment['current_day'] . " completed for calf " . $treatment['calf_id'] . " - Day " . $newDay . " of " . $treatment['duration_days'];
                    }
                }
                
                $this->logActivity(
                    "Completed day " . $treatment['current_day'] . " of treatment for calf " . $treatment['calf_id'],
                    $treatment['calf_id'],
                    $treatmentPlanId
                );

            } catch (Exception $e) {
                $_SESSION['error_message'] = "❌ Error: " . $e->getMessage();
            }
        }

        header('Location: /public/treatment');
        exit;
    }

    public function cancel() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['treatment_plan_id'])) {
            try {
                $treatmentPlanId = intval($_POST['treatment_plan_id']);
                $cancelReason = trim($_POST['cancel_reason'] ?? 'Cancelled by user');
                
                $treatment = $this->db->fetch("
                    SELECT tp.*, c.calf_id 
                    FROM treatment_plans tp 
                    JOIN calves c ON tp.calf_id = c.id 
                    WHERE tp.id = ?",
                    [$treatmentPlanId]
                );
                
                if (!$treatment) {
                    throw new Exception("Treatment plan not found");
                }
                
                $this->db->query("
                    UPDATE treatment_plans 
                    SET status = 'cancelled', 
                        notes = CONCAT(COALESCE(notes, ''), '\nCancelled: ', ?), 
                        updated_at = NOW() 
                    WHERE id = ?",
                    [$cancelReason, $treatmentPlanId]
                );
                
                $this->db->query("
                    UPDATE calf_events 
                    SET status = 'cancelled' 
                    WHERE calf_id = ? 
                    AND due_date >= CURDATE() 
                    AND status = 'pending'",
                    [$treatment['calf_id']]
                );
                
                $this->logActivity(
                    "Cancelled treatment for calf " . $treatment['calf_id'] . ": " . $cancelReason,
                    $treatment['calf_id'],
                    $treatmentPlanId
                );
                
                $_SESSION['success_message'] = "✅ Treatment cancelled for calf " . $treatment['calf_id'];

            } catch (Exception $e) {
                $_SESSION['error_message'] = "❌ Error: " . $e->getMessage();
            }
        }

        header('Location: /public/treatment');
        exit;
    }

    public function add() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        $success = false;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_treatment'])) {
            try {
                $calfId = intval($_POST['calf_id']);
                $treatmentType = $_POST['treatment_type'];
                $treatmentName = trim($_POST['treatment_name']);
                $durationDays = intval($_POST['duration_days']);
                $notes = trim($_POST['notes'] ?? '');
                
                if ($calfId <= 0 || empty($treatmentType) || empty($treatmentName) || $durationDays < 1) {
                    throw new Exception("All fields are required and duration must be at least 1 day");
                }
                
                $this->db->query("
                    INSERT INTO treatment_plans (calf_id, treatment_type, treatment_name, is_custom, start_date, duration_days, current_day, status, notes, created_by, created_at)
                    VALUES (?, ?, ?, 1, CURDATE(), ?, 1, 'active', ?, ?, NOW())",
                    [$calfId, $treatmentType, $treatmentName, $durationDays, $notes, $_SESSION['user_id']]
                );
                
                $treatmentPlanId = $this->db->lastInsertId();
                $eventId = $this->getOrCreateTreatmentEvent($treatmentType, $treatmentName);
                
                for ($day = 0; $day < $durationDays; $day++) {
                    $dueDate = date('Y-m-d', strtotime("+$day days"));
                    $this->db->query("
                        INSERT INTO calf_events (calf_id, event_id, due_date, status, created_at)
                        VALUES (?, ?, ?, 'pending', NOW())",
                        [$calfId, $eventId, $dueDate]
                    );
                }
                
                $calf = $this->db->fetch("SELECT calf_id FROM calves WHERE id = ?", [$calfId]);
                $this->logActivity(
                    "Started " . $durationDays . "-day " . $treatmentName . " treatment for calf " . $calf['calf_id'],
                    $calfId,
                    $treatmentPlanId
                );
                
                $_SESSION['success_message'] = "✅ Treatment plan created successfully!";
                header('Location: /public/treatment');
                exit;

            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
        
        $calves = $this->db->fetchAll("SELECT id, calf_id FROM calves WHERE status = 'active' ORDER BY calf_id ASC");
        $this->renderAddTreatmentForm($calves, $success, $error);
    }

    public function history() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        $treatments = $this->db->fetchAll("
            SELECT 
                tp.*,
                c.calf_id,
                b.name as batch_name,
                u.name as created_by_name,
                DATEDIFF(NOW(), tp.start_date) as days_elapsed
            FROM treatment_plans tp
            JOIN calves c ON tp.calf_id = c.id
            LEFT JOIN batches b ON c.batch_id = b.id
            JOIN users u ON tp.created_by = u.id
            WHERE tp.status IN ('completed', 'cancelled')
            ORDER BY tp.updated_at DESC
            LIMIT 100
        ");

        $this->renderTreatmentHistory($treatments);
    }

    private function getOrCreateTreatmentEvent($treatmentType, $treatmentName) {
        $eventName = $treatmentName . " Treatment";
        
        $event = $this->db->fetch(
            "SELECT id FROM events WHERE name = ? AND type = 'treatment'",
            [$eventName]
        );
        
        if ($event) {
            return $event['id'];
        }
        
        $this->db->query(
            "INSERT INTO events (name, type, age_start, age_end, is_active, created_by, created_at) 
             VALUES (?, 'treatment', 0, 365, 1, ?, NOW())",
            [$eventName, $_SESSION['user_id']]
        );
        
        return $this->db->lastInsertId();
    }

    private function logActivity($description, $calfId = null, $treatmentPlanId = null) {
        $this->db->query(
            "INSERT INTO activity_logs (user_id, activity_type, description, calf_id, treatment_plan_id, ip_address, created_at) 
             VALUES (?, 'treatment', ?, ?, ?, ?, NOW())",
            [
                $_SESSION['user_id'],
                $description,
                $calfId,
                $treatmentPlanId,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]
        );
    }

    private function renderTreatmentDashboard($treatments) {
        require_once BASE_PATH . '/app/core/ModernNavbar.php';
        $navbar = new ModernNavbar();
        
        ob_start();
        $navbar->render('treatment');
        $navbarHtml = ob_get_clean();
        
        $successMessage = $_SESSION['success_message'] ?? null;
        $errorMessage = $_SESSION['error_message'] ?? null;
        unset($_SESSION['success_message'], $_SESSION['error_message']);
        
        echo '<!DOCTYPE html><html><head><title>Treatment Management</title><link rel="stylesheet" href="/public/assets/css/style.css"></head><body>';
        echo $navbarHtml;
        echo '<div style="max-width: 1400px; margin: 0 auto; padding: 2rem; padding-top: 100px;">';
        echo '<h1 style="text-align: center; margin-bottom: 2rem;">💊 Treatment Management</h1>';
        
        if ($successMessage) {
            echo '<div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">✅ ' . htmlspecialchars($successMessage) . '</div>';
        }
        
        if ($errorMessage) {
            echo '<div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">⚠️ ' . htmlspecialchars($errorMessage) . '</div>';
        }
        
        echo '<div style="margin-bottom: 2rem; text-align: center;">
            <a href="/public/treatment/add" class="btn btn-primary">Add New Treatment</a>
            <a href="/public/treatment/history" class="btn btn-secondary">View History</a>
        </div>';
        
        if (empty($treatments)) {
            echo '<div style="text-align: center; padding: 3rem; background: white; border-radius: 12px;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">💚</div>
                <h3>No Active Treatments</h3>
                <p>All calves are healthy!</p>
            </div>';
        } else {
            echo '<table class="table"><thead><tr><th>Calf</th><th>Treatment</th><th>Progress</th><th>Started</th><th>Actions</th></tr></thead><tbody>';
            
            foreach ($treatments as $treatment) {
                $progress = round((($treatment['current_day'] - 1) / $treatment['duration_days']) * 100);
                echo '<tr>
                    <td><strong>' . htmlspecialchars($treatment['calf_id']) . '</strong></td>
                    <td>' . htmlspecialchars($treatment['treatment_name']) . '<br><small>Day ' . $treatment['current_day'] . ' of ' . $treatment['duration_days'] . '</small></td>
                    <td><div style="background: #e9ecef; height: 8px; border-radius: 4px; overflow: hidden;">
                        <div style="background: #2196F3; height: 100%; width: ' . $progress . '%;"></div>
                    </div><small>' . $progress . '%</small></td>
                    <td>' . date('M j, Y', strtotime($treatment['start_date'])) . '</td>
                    <td>
                        <form method="post" action="/public/treatment/complete-day" style="display: inline;">
                            <input type="hidden" name="treatment_plan_id" value="' . $treatment['id'] . '">
                            <button type="submit" class="btn btn-primary btn-sm">✅ Complete Day</button>
                        </form>
                        <button class="btn btn-secondary btn-sm" onclick="cancelTreatment(' . $treatment['id'] . ', \'' . htmlspecialchars($treatment['calf_id']) . '\')">❌ Cancel</button>
                    </td>
                </tr>';
            }
            
            echo '</tbody></table>';
        }
        
        echo '</div>';
        echo '<script>
        function cancelTreatment(treatmentId, calfId) {
            const reason = prompt("Cancel treatment for " + calfId + "?\\n\\nPlease enter reason:");
            if (reason && reason.trim() !== "") {
                const form = document.createElement("form");
                form.method = "POST";
                form.action = "/public/treatment/cancel";
                
                const idInput = document.createElement("input");
                idInput.type = "hidden";
                idInput.name = "treatment_plan_id";
                idInput.value = treatmentId;
                
                const reasonInput = document.createElement("input");
                reasonInput.type = "hidden";
                reasonInput.name = "cancel_reason";
                reasonInput.value = reason;
                
                form.appendChild(idInput);
                form.appendChild(reasonInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        </script></body></html>';
    }

    private function renderAddTreatmentForm($calves, $success, $error) {
        require_once BASE_PATH . '/app/core/ModernNavbar.php';
        $navbar = new ModernNavbar();
        
        ob_start();
        $navbar->render('treatment');
        $navbarHtml = ob_get_clean();
        
        echo '<!DOCTYPE html><html><head><title>Add Treatment</title><link rel="stylesheet" href="/public/assets/css/style.css"></head><body>';
        echo $navbarHtml;
        echo '<div style="max-width: 600px; margin: 0 auto; padding: 2rem; padding-top: 100px;">';
        echo '<h1>Add Treatment Plan</h1>';
        
        if ($error) {
            echo '<div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">Error: ' . htmlspecialchars($error) . '</div>';
        }
        
        echo '<form method="post" style="background: white; padding: 2rem; border-radius: 12px;">
            <input type="hidden" name="add_treatment" value="1">
            <div class="form-group">
                <label class="form-label">Calf *</label>
                <select name="calf_id" class="form-control" required><option value="">Select Calf</option>';
        
        foreach ($calves as $calf) {
            echo '<option value="' . $calf['id'] . '">' . htmlspecialchars($calf['calf_id']) . '</option>';
        }
        
        echo '</select></div>
            <div class="form-group">
                <label class="form-label">Treatment Type *</label>
                <select name="treatment_type" class="form-control" required>
                    <option value="electrolyte">Electrolyte</option>
                    <option value="antibiotic">Antibiotic</option>
                    <option value="medication">Medication</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Treatment Name *</label>
                <input type="text" name="treatment_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Duration (Days) *</label>
                <input type="number" name="duration_days" class="form-control" required min="1" max="30" value="3">
            </div>
            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="3"></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Create Treatment</button>
            <a href="/public/treatment" class="btn btn-secondary" style="width: 100%; margin-top: 0.5rem; text-align: center; display: block;">Cancel</a>
        </form></div></body></html>';
    }

    private function renderTreatmentHistory($treatments) {
        require_once BASE_PATH . '/app/core/ModernNavbar.php';
        $navbar = new ModernNavbar();
        
        ob_start();
        $navbar->render('treatment');
        $navbarHtml = ob_get_clean();
        
        echo '<!DOCTYPE html><html><head><title>Treatment History</title><link rel="stylesheet" href="/public/assets/css/style.css"></head><body>';
        echo $navbarHtml;
        echo '<div style="max-width: 1200px; margin: 0 auto; padding: 2rem; padding-top: 100px;">';
        echo '<h1>Treatment History</h1>';
        echo '<a href="/public/treatment" class="btn btn-primary" style="margin-bottom: 2rem;">Back to Active Treatments</a>';
        
        if (empty($treatments)) {
            echo '<div style="text-align: center; padding: 3rem; background: white; border-radius: 12px;"><p>No treatment history found.</p></div>';
        } else {
            echo '<table class="table"><thead><tr><th>Calf</th><th>Treatment</th><th>Duration</th><th>Status</th><th>Started</th></tr></thead><tbody>';
            
            foreach ($treatments as $treatment) {
                $statusColor = $treatment['status'] === 'completed' ? '#28a745' : '#6c757d';
                echo '<tr>
                    <td><strong>' . htmlspecialchars($treatment['calf_id']) . '</strong></td>
                    <td>' . htmlspecialchars($treatment['treatment_name']) . '</td>
                    <td>' . $treatment['duration_days'] . ' days</td>
                    <td><span style="color: ' . $statusColor . '; font-weight: 600;">' . ucfirst($treatment['status']) . '</span></td>
                    <td>' . date('M j, Y', strtotime($treatment['start_date'])) . '</td>
                </tr>';
            }
            
            echo '</tbody></table>';
        }
        
        echo '</div></body></html>';
    }
}