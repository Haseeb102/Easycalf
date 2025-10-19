<?php
class ElectrolytesController {
    private $db;
    private $auth;

    public function __construct() {
        $this->db = new Database();
        $this->auth = new Auth();
    }

    public function quick() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        $success = false;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calf_id'])) {
            try {
                $calfId = $_POST['calf_id'];
                
                // Get calf database ID
                $calf = $this->db->fetch("SELECT id, calf_id FROM calves WHERE calf_id = ? AND status = 'active'", [$calfId]);
                if (!$calf) {
                    throw new Exception("Calf '$calfId' not found or not active");
                }

                // Check if there's already an active electrolyte treatment
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

                // Create 3-day electrolyte treatment
                $this->db->query("
                    INSERT INTO treatment_plans (calf_id, treatment_type, treatment_name, is_custom, start_date, duration_days, current_day, status, notes, created_by, created_at)
                    VALUES (?, 'electrolyte', 'Electrolyte Solution', 0, CURDATE(), 3, 1, 'active', 'Quick electrolyte treatment (3 days)', ?, NOW())",
                    [$calf['id'], $_SESSION['user_id']]
                );

                $treatmentPlanId = $this->db->lastInsertId();

                // Get or create treatment event
                $eventId = $this->getOrCreateTreatmentEvent('electrolyte', 'Electrolyte Solution');

                // Create tasks for 3 days
                for ($day = 0; $day < 3; $day++) {
                    $dueDate = date('Y-m-d', strtotime("+$day days"));
                    $this->db->query("
                        INSERT INTO calf_events (calf_id, event_id, due_date, status, created_at)
                        VALUES (?, ?, ?, 'pending', NOW())",
                        [$calf['id'], $eventId, $dueDate]
                    );
                }

                // Log activity
                $this->logActivity("Started 3-day electrolyte treatment for calf " . $calf['calf_id'], $calf['id'], $treatmentPlanId);
                
                $success = true;
                $_SESSION['success_message'] = "✅ 3-day electrolyte treatment started for calf " . $calf['calf_id'];

            } catch (Exception $e) {
                $error = $e->getMessage();
                $_SESSION['error_message'] = "❌ Error: " . $e->getMessage();
            }

            header('Location: /public/calves');
            exit;
        }
    }

    public function bulk() {
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
                    // Check if calf exists and get database ID
                    $calf = $this->db->fetch("SELECT id FROM calves WHERE id = ? AND status = 'active'", [$calfId]);
                    if (!$calf) continue;

                    // Check for existing treatment
                    $existingTreatment = $this->db->fetch("
                        SELECT id FROM treatment_plans 
                        WHERE calf_id = ? 
                        AND treatment_type = 'electrolyte' 
                        AND status = 'active'",
                        [$calfId]
                    );

                    if ($existingTreatment) continue;

                    // Create treatment
                    $this->db->query("
                        INSERT INTO treatment_plans (calf_id, treatment_type, treatment_name, is_custom, start_date, duration_days, current_day, status, notes, created_by, created_at)
                        VALUES (?, 'electrolyte', 'Electrolyte Solution', 0, CURDATE(), ?, 1, 'active', 'Bulk electrolyte treatment', ?, NOW())",
                        [$calfId, $durationDays, $_SESSION['user_id']]
                    );

                    // Create tasks
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

    public function undo() {
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

                // Find active electrolyte treatment
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

                // Cancel the treatment
                $this->db->query("
                    UPDATE treatment_plans 
                    SET status = 'cancelled', 
                        notes = CONCAT(notes, '\nCancelled by user'), 
                        updated_at = NOW() 
                    WHERE id = ?",
                    [$treatment['id']]
                );

                // Cancel pending calf_events
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

    // Helper methods
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
        $this->db->query("
            INSERT INTO activity_logs (user_id, activity_type, description, calf_id, treatment_plan_id, ip_address, created_at)
            VALUES (?, 'electrolyte', ?, ?, ?, ?, NOW())",
            [
                $_SESSION['user_id'],
                $description,
                $calfId,
                $treatmentPlanId,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]
        );
    }
}
?>