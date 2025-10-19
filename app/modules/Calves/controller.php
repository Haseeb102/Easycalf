<?php
/**
 * Calves Controller - COMPLETE FIXED VERSION
 * 
 * CRITICAL FIXES:
 * 1. Soft delete now CANCELS all pending events (fixes task count issue)
 * 2. Soft delete CANCELS all active treatments
 * 3. Permanent delete removes ALL related records completely
 * 4. Bulk delete handles event cleanup properly
 * 5. Import ignores deleted calves when checking duplicates
 * 6. All queries exclude deleted calves from main views
 */
class CalvesController {
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

        // Get only active calves (exclude deleted)
        $calves = $this->db->fetchAll("
            SELECT c.*, b.name as batch_name, 
                   DATEDIFF(NOW(), c.birth_date) as age_days
            FROM calves c
            LEFT JOIN batches b ON c.batch_id = b.id
            WHERE c.status != 'deleted'
            ORDER BY c.created_at DESC
        ");

        $batches = $this->db->fetchAll("SELECT id, name FROM batches WHERE is_active = 1");

        // Check for active electrolyte treatments - ONLY for active calves
        $activeElectrolyteTreatments = [];
        try {
            $activeElectrolyteTreatments = $this->db->fetchAll("
                SELECT tp.id, c.calf_id 
                FROM treatment_plans tp 
                JOIN calves c ON tp.calf_id = c.id 
                WHERE tp.treatment_type = 'electrolyte' 
                AND tp.status = 'active'
                AND c.status != 'deleted'
            ");
        } catch (Exception $e) {
            error_log("Treatment query error: " . $e->getMessage());
        }

        $electrolyteCalfIds = array_column($activeElectrolyteTreatments, 'calf_id');

        include BASE_PATH . '/app/modules/Calves/views/list.php';
    }

    public function add() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        $success = false;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_calf'])) {
            try {
                $calfId = trim($_POST['calf_id']);
                $birthDate = $_POST['birth_date'];
                $sex = $_POST['sex'];
                $damId = trim($_POST['dam_id']) ?: null;
                $birthWeight = $_POST['birth_weight'] ?: null;
                $healthStatus = $_POST['health_status'];
                $batchId = $_POST['batch_id'] ?: null;
                $breed = trim($_POST['breed'] ?? '') ?: null;
                $penLocation = trim($_POST['pen_location'] ?? '') ?: null;

                if (empty($calfId) || empty($birthDate) || empty($sex)) {
                    throw new Exception("Calf ID, birth date, and sex are required");
                }

                // Check for ACTIVE calves only (ignore deleted)
                $existing = $this->db->fetch(
                    "SELECT id FROM calves WHERE calf_id = ? AND status != 'deleted'", 
                    [$calfId]
                );
                
                if ($existing) {
                    throw new Exception("Calf ID already exists. Please use a unique ID.");
                }

                $this->db->query("
                    INSERT INTO calves (calf_id, birth_date, sex, dam_id, birth_weight, health_status, status, batch_id, breed, pen_location, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, NOW())",
                    [$calfId, $birthDate, $sex, $damId, $birthWeight, $healthStatus, $batchId, $breed, $penLocation, $_SESSION['user_id']]
                );

                $newCalfId = $this->db->lastInsertId();
                $this->scheduleEventsForCalf($newCalfId, $birthDate);
                $this->logActivity("Added new calf: $calfId", $newCalfId);

                $success = true;
                $_SESSION['success_message'] = "Calf $calfId added successfully and events scheduled!";

            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        $batches = $this->db->fetchAll("SELECT id, name FROM batches WHERE is_active = 1");
        $suggestedId = $this->generateCalfId();

        include BASE_PATH . '/app/modules/Calves/views/add.php';
    }

    public function edit($id = null) {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        if (!$id) {
            header('Location: /public/calves');
            exit;
        }

        $success = false;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_calf'])) {
            try {
                $healthStatus = $_POST['health_status'];
                $status = $_POST['status'];
                $batchId = $_POST['batch_id'] ?: null;
                $birthWeight = $_POST['birth_weight'] ?: null;
                $breed = trim($_POST['breed'] ?? '') ?: null;
                $penLocation = trim($_POST['pen_location'] ?? '') ?: null;

                $this->db->query("
                    UPDATE calves 
                    SET health_status = ?, status = ?, batch_id = ?, birth_weight = ?, breed = ?, pen_location = ?, updated_at = NOW() 
                    WHERE id = ?",
                    [$healthStatus, $status, $batchId, $birthWeight, $breed, $penLocation, $id]
                );

                $success = true;
                $_SESSION['success_message'] = "Calf updated successfully!";
                header('Location: /public/calves/passport/' . $id);
                exit;

            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        $calf = $this->db->fetch("SELECT * FROM calves WHERE id = ? AND status != 'deleted'", [$id]);
        if (!$calf) {
            $_SESSION['error_message'] = "Calf not found or has been deleted";
            header('Location: /public/calves');
            exit;
        }

        $batches = $this->db->fetchAll("SELECT id, name FROM batches WHERE is_active = 1");
        include BASE_PATH . '/app/modules/Calves/views/edit.php';
    }

    public function passport($id = null) {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        if (!$id) {
            header('Location: /public/calves');
            exit;
        }

        // Allow viewing passport for ANY calf (including deleted) for historical records
        $calf = $this->db->fetch("
            SELECT c.*, 
                   b.name as batch_name,
                   b.location as batch_location, 
                   DATEDIFF(NOW(), c.birth_date) as age_days,
                   FLOOR(DATEDIFF(NOW(), c.birth_date) / 7) as age_weeks,
                   u.name as created_by_name
            FROM calves c
            LEFT JOIN batches b ON c.batch_id = b.id
            LEFT JOIN users u ON c.created_by = u.id
            WHERE c.id = ?",
            [$id]
        );

        if (!$calf) {
            $_SESSION['error_message'] = "Calf not found";
            header('Location: /public/calves');
            exit;
        }

        // Get life events timeline
        $events = $this->db->fetchAll("
            SELECT ce.*, 
                   e.name as event_name, 
                   e.type as event_type,
                   u.name as completed_by_name
            FROM calf_events ce
            JOIN events e ON ce.event_id = e.id
            LEFT JOIN users u ON ce.completed_by = u.id
            WHERE ce.calf_id = ?
            ORDER BY ce.due_date DESC, ce.created_at DESC",
            [$id]
        );

        // Get treatment history
        $treatments = [];
        try {
            $treatments = $this->db->fetchAll("
                SELECT tp.*,
                       u.name as created_by_name
                FROM treatment_plans tp
                LEFT JOIN users u ON tp.created_by = u.id
                WHERE tp.calf_id = ?
                ORDER BY tp.start_date DESC",
                [$id]
            );
        } catch (Exception $e) {
            error_log("Treatment query error: " . $e->getMessage());
        }

        // Get weight history if available
        $weights = [];
        try {
            $weights = $this->db->fetchAll("
                SELECT * FROM calf_weights 
                WHERE calf_id = ? 
                ORDER BY weight_date DESC",
                [$id]
            );
        } catch (Exception $e) {
            // Weight table may not exist yet
        }

        include BASE_PATH . '/app/modules/Calves/views/passport.php';
    }

    // ==================== ENHANCED DELETE METHODS ====================

    public function delete() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calf_id'])) {
            try {
                $calfId = intval($_POST['calf_id']);
                $isPermanent = isset($_POST['permanent']) && $_POST['permanent'] === 'yes';
                
                // Get calf info before deletion
                $calf = $this->db->fetch("SELECT calf_id, status FROM calves WHERE id = ?", [$calfId]);
                
                if (!$calf) {
                    throw new Exception("Calf not found");
                }
                
                if ($isPermanent) {
                    // ========== PERMANENT DELETE ==========
                    // Remove ALL records from ALL tables
                    
                    // Step 1: Delete calf events
                    $this->db->query("DELETE FROM calf_events WHERE calf_id = ?", [$calfId]);
                    
                    // Step 2: Delete treatment completions
                    $this->db->query("DELETE FROM treatment_completions WHERE calf_id = ?", [$calfId]);
                    
                    // Step 3: Delete treatment plans
                    $this->db->query("DELETE FROM treatment_plans WHERE calf_id = ?", [$calfId]);
                    
                    // Step 4: Delete weight records (if table exists)
                    try {
                        $this->db->query("DELETE FROM calf_weights WHERE calf_id = ?", [$calfId]);
                    } catch (Exception $e) {
                        // Table might not exist
                    }
                    
                    // Step 5: Delete the calf itself
                    $this->db->query("DELETE FROM calves WHERE id = ?", [$calfId]);
                    
                    $this->logActivity("PERMANENTLY deleted calf: " . $calf['calf_id'] . " (ALL RECORDS REMOVED)", null);
                    $_SESSION['success_message'] = "✅ Calf " . $calf['calf_id'] . " and ALL related records permanently deleted from database";
                    
                } else {
                    // ========== SOFT DELETE WITH COMPLETE CLEANUP ==========
                    
                    // Step 1: Mark calf as deleted
                    $this->db->query(
                        "UPDATE calves SET status = 'deleted', updated_at = NOW() WHERE id = ?",
                        [$calfId]
                    );
                    
                    // Step 2: CRITICAL FIX - Cancel ALL pending calf_events
                    $cancelledEvents = $this->db->query(
                        "UPDATE calf_events 
                         SET status = 'cancelled', 
                             completed_date = NOW(),
                             completed_by = ?,
                             completed_notes = 'Auto-cancelled: Calf deleted'
                         WHERE calf_id = ? 
                         AND status = 'pending'",
                        [$_SESSION['user_id'], $calfId]
                    );
                    
                    // Step 3: Cancel ALL active treatments
                    $cancelledTreatments = $this->db->query(
                        "UPDATE treatment_plans 
                         SET status = 'cancelled', 
                             notes = CONCAT(COALESCE(notes, ''), '\nAuto-cancelled: Calf deleted on ', NOW()),
                             updated_at = NOW()
                         WHERE calf_id = ? 
                         AND status = 'active'",
                        [$calfId]
                    );

                    $eventsCount = $cancelledEvents->rowCount();
                    $treatmentsCount = $cancelledTreatments->rowCount();

                    $this->logActivity("Soft deleted calf: " . $calf['calf_id'] . " | Cancelled $eventsCount events and $treatmentsCount treatments", $calfId);
                    $_SESSION['success_message'] = "✅ Calf " . $calf['calf_id'] . " marked as deleted | Cancelled $eventsCount pending tasks and $treatmentsCount active treatments";
                }

            } catch (Exception $e) {
                $_SESSION['error_message'] = "❌ Error deleting calf: " . $e->getMessage();
                error_log("Delete error: " . $e->getMessage());
            }
        }

        header('Location: /public/calves');
        exit;
    }

    public function bulkDelete() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calf_ids'])) {
            try {
                $calfIds = $_POST['calf_ids'];
                $isPermanent = isset($_POST['permanent']) && $_POST['permanent'] === 'yes';
                
                if (empty($calfIds) || !is_array($calfIds)) {
                    throw new Exception("No calves selected");
                }

                // Sanitize IDs
                $calfIds = array_map('intval', $calfIds);
                $calfIds = array_filter($calfIds, function($id) {
                    return $id > 0;
                });

                if (empty($calfIds)) {
                    throw new Exception("Invalid calf IDs provided");
                }

                // Get calf info before deletion for logging
                $placeholders = str_repeat('?,', count($calfIds) - 1) . '?';
                $calvesList = $this->db->fetchAll(
                    "SELECT id, calf_id FROM calves WHERE id IN ($placeholders)",
                    $calfIds
                );

                if (empty($calvesList)) {
                    throw new Exception("No calves found with the provided IDs");
                }

                if ($isPermanent) {
                    // ========== PERMANENT BULK DELETE ==========
                    $deletedCount = 0;
                    foreach ($calvesList as $calf) {
                        try {
                            $this->db->query("DELETE FROM calf_events WHERE calf_id = ?", [$calf['id']]);
                            $this->db->query("DELETE FROM treatment_completions WHERE calf_id = ?", [$calf['id']]);
                            $this->db->query("DELETE FROM treatment_plans WHERE calf_id = ?", [$calf['id']]);
                            try {
                                $this->db->query("DELETE FROM calf_weights WHERE calf_id = ?", [$calf['id']]);
                            } catch (Exception $e) {
                                // Table might not exist
                            }
                            $this->db->query("DELETE FROM calves WHERE id = ?", [$calf['id']]);
                            $this->logActivity("PERMANENTLY deleted calf: " . $calf['calf_id'], null);
                            $deletedCount++;
                        } catch (Exception $e) {
                            error_log("Failed to permanently delete calf " . $calf['calf_id'] . ": " . $e->getMessage());
                        }
                    }
                    
                    $_SESSION['success_message'] = "✅ $deletedCount calves and all related records PERMANENTLY deleted from database";
                    
                } else {
                    // ========== SOFT BULK DELETE WITH COMPLETE CLEANUP ==========
                    
                    // Step 1: Mark calves as deleted
                    $result = $this->db->query(
                        "UPDATE calves SET status = 'deleted', updated_at = NOW() WHERE id IN ($placeholders)",
                        $calfIds
                    );
                    
                    $deletedCount = $result->rowCount();
                    
                    // Step 2: CRITICAL FIX - Cancel ALL pending events for these calves
                    $cancelledEvents = $this->db->query(
                        "UPDATE calf_events 
                         SET status = 'cancelled', 
                             completed_date = NOW(),
                             completed_by = ?,
                             completed_notes = 'Auto-cancelled: Calf deleted'
                         WHERE calf_id IN ($placeholders) 
                         AND status = 'pending'",
                        array_merge([$_SESSION['user_id']], $calfIds)
                    );
                    
                    // Step 3: Cancel ALL active treatments for these calves
                    $cancelledTreatments = $this->db->query(
                        "UPDATE treatment_plans 
                         SET status = 'cancelled', 
                             notes = CONCAT(COALESCE(notes, ''), '\nAuto-cancelled: Calf deleted on ', NOW()),
                             updated_at = NOW()
                         WHERE calf_id IN ($placeholders) 
                         AND status = 'active'",
                        $calfIds
                    );
                    
                    $eventsCount = $cancelledEvents->rowCount();
                    $treatmentsCount = $cancelledTreatments->rowCount();
                    
                    foreach ($calvesList as $calf) {
                        $this->logActivity("Soft deleted calf: " . $calf['calf_id'], $calf['id']);
                    }
                    
                    $_SESSION['success_message'] = "✅ $deletedCount calves marked as deleted | Cancelled $eventsCount pending tasks and $treatmentsCount active treatments";
                }

            } catch (Exception $e) {
                error_log("Bulk Delete Error: " . $e->getMessage());
                $_SESSION['error_message'] = "❌ Error: " . $e->getMessage();
            }
        } else {
            $_SESSION['error_message'] = "❌ Invalid request. No calves selected.";
        }

        header('Location: /public/calves');
        exit;
    }

    // ==================== IMPORT METHOD ====================

    public function import() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
            try {
                $file = $_FILES['import_file'];
                
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("File upload error: " . $file['error']);
                }
                
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($extension, ['csv', 'txt'])) {
                    throw new Exception("Only CSV files are supported. Please upload a .csv file.");
                }
                
                $duplicateAction = $_POST['duplicate_action'] ?? 'skip';
                
                $results = $this->processImportFile($file['tmp_name'], $file['name'], $duplicateAction);
                
                $_SESSION['import_results'] = $results;
                
                @unlink($file['tmp_name']);
                
                header('Location: /public/calves/import');
                exit;
                
            } catch (Exception $e) {
                $_SESSION['error_message'] = "Import failed: " . $e->getMessage();
                error_log("Import error: " . $e->getMessage());
            }
        }

        $this->showImportForm();
    }

    private function processImportFile($filePath, $filename, $duplicateAction) {
        $results = [
            'total_rows' => 0,
            'successful' => 0,
            'failed' => 0,
            'duplicates' => 0,
            'skipped' => 0,
            'updated' => 0,
            'errors' => [],
            'successful_imports' => [],
            'duplicate_list' => [],
            'failed_list' => []
        ];

        try {
            $file = fopen($filePath, 'r');
            if (!$file) {
                throw new Exception("Could not open file: $filePath");
            }

            $data = [];
            $headers = null;
            
            $firstLine = fgets($file);
            rewind($file);
            $delimiter = $this->detectDelimiter($firstLine);
            
            while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
                if (empty(array_filter($row))) {
                    continue;
                }
                
                if ($headers === null) {
                    $headers = array_map('trim', $row);
                } else {
                    $data[] = $row;
                }
            }
            
            fclose($file);
            
            $results['total_rows'] = count($data);
            
            $columnMap = $this->mapColumns($headers);
            
            $validation = $this->validateRequiredFields($columnMap);
            if (!$validation['valid']) {
                throw new Exception("Missing required columns: " . implode(', ', $validation['missing_fields']));
            }
            
            foreach ($data as $index => $row) {
                $rowNumber = $index + 2;
                $this->processImportRow($row, $columnMap, $rowNumber, $duplicateAction, $results);
            }
            
        } catch (Exception $e) {
            $results['errors'][] = "Fatal error: " . $e->getMessage();
        }
        
        return $results;
    }

    private function processImportRow($row, $columnMap, $rowNumber, $duplicateAction, &$results) {
        try {
            $calfData = $this->extractImportData($row, $columnMap);
            
            $validation = $this->validateImportRow($calfData, $rowNumber);
            
            if (!$validation['valid']) {
                $results['failed']++;
                $results['failed_list'][] = [
                    'row' => $rowNumber,
                    'calf_id' => isset($calfData['calf_id']) ? $calfData['calf_id'] : 'Unknown',
                    'errors' => $validation['errors']
                ];
                return;
            }
            
            $calfData = $validation['data'];
            
            // CRITICAL FIX: Only check for ACTIVE calves, ignore deleted ones
            $existing = $this->db->fetch(
                "SELECT id, calf_id, status FROM calves WHERE calf_id = ? AND status != 'deleted'",
                [$calfData['calf_id']]
            );
            
            if ($existing) {
                $this->handleImportDuplicate($existing, $calfData, $rowNumber, $duplicateAction, $results);
            } else {
                $this->insertImportCalf($calfData, $rowNumber, $results);
            }
            
        } catch (Exception $e) {
            $results['failed']++;
            $results['failed_list'][] = [
                'row' => $rowNumber,
                'calf_id' => isset($calfData['calf_id']) ? $calfData['calf_id'] : 'Unknown',
                'errors' => [$e->getMessage()]
            ];
        }
    }

    // ==================== HELPER METHODS ====================

    private function extractImportData($row, $columnMap) {
        $calfData = [];
        
        foreach ($columnMap as $colIndex => $dbField) {
            if (isset($row[$colIndex]) && trim($row[$colIndex]) !== '') {
                $calfData[$dbField] = trim($row[$colIndex]);
            }
        }
        
        if (!isset($calfData['health_status'])) {
            $calfData['health_status'] = 'healthy';
        } else {
            $calfData['health_status'] = $this->parseHealthStatus($calfData['health_status']);
        }
        
        if (!isset($calfData['status'])) {
            $calfData['status'] = 'active';
        }
        
        return $calfData;
    }

    private function handleImportDuplicate($existing, $calfData, $rowNumber, $duplicateAction, &$results) {
        switch ($duplicateAction) {
            case 'skip':
                $results['duplicates']++;
                $results['duplicate_list'][] = [
                    'row' => $rowNumber,
                    'calf_id' => $calfData['calf_id'],
                    'action' => 'Skipped - Active calf exists'
                ];
                break;
            
            case 'update':
                $this->updateImportCalf($existing['id'], $calfData, $rowNumber);
                $results['updated']++;
                $results['duplicate_list'][] = [
                    'row' => $rowNumber,
                    'calf_id' => $calfData['calf_id'],
                    'action' => 'Updated existing calf'
                ];
                break;
            
            default:
                $results['skipped']++;
        }
    }

    private function insertImportCalf($calfData, $rowNumber, &$results) {
        try {
            $this->db->query("
                INSERT INTO calves (
                    calf_id, birth_date, sex, dam_id, birth_weight, 
                    health_status, status, batch_id, breed, pen_location,
                    created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ", [
                $calfData['calf_id'],
                $calfData['birth_date'],
                $calfData['sex'],
                isset($calfData['dam_id']) ? $calfData['dam_id'] : null,
                isset($calfData['birth_weight']) ? $calfData['birth_weight'] : null,
                isset($calfData['health_status']) ? $calfData['health_status'] : 'healthy',
                isset($calfData['status']) ? $calfData['status'] : 'active',
                isset($calfData['batch_id']) ? $calfData['batch_id'] : null,
                isset($calfData['breed']) ? $calfData['breed'] : null,
                isset($calfData['pen_location']) ? $calfData['pen_location'] : null,
                $_SESSION['user_id']
            ]);
            
            $newCalfId = $this->db->lastInsertId();
            $this->scheduleEventsForCalf($newCalfId, $calfData['birth_date']);
            
            $results['successful']++;
            $results['successful_imports'][] = [
                'row' => $rowNumber,
                'calf_id' => $calfData['calf_id']
            ];
            
        } catch (Exception $e) {
            $results['failed']++;
            $results['failed_list'][] = [
                'row' => $rowNumber,
                'calf_id' => $calfData['calf_id'],
                'errors' => ["Database error: " . $e->getMessage()]
            ];
        }
    }

    private function updateImportCalf($calfId, $calfData, $rowNumber) {
        try {
            $this->db->query("
                UPDATE calves SET
                    birth_date = ?,
                    sex = ?,
                    dam_id = ?,
                    birth_weight = ?,
                    health_status = ?,
                    breed = ?,
                    pen_location = ?,
                    updated_at = NOW()
                WHERE id = ?
            ", [
                $calfData['birth_date'],
                $calfData['sex'],
                isset($calfData['dam_id']) ? $calfData['dam_id'] : null,
                isset($calfData['birth_weight']) ? $calfData['birth_weight'] : null,
                isset($calfData['health_status']) ? $calfData['health_status'] : 'healthy',
                isset($calfData['breed']) ? $calfData['breed'] : null,
                isset($calfData['pen_location']) ? $calfData['pen_location'] : null,
                $calfId
            ]);
            
        } catch (Exception $e) {
            throw new Exception("Failed to update calf: " . $e->getMessage());
        }
    }

    private function mapColumns($headers) {
        $columnMappings = [
            'calf_id' => ['calf_id', 'calfid', 'calf id', 'id', 'tag', 'tag number', 'tag_number', 'animal id', 'animal_id', 'eartag', 'ear tag'],
            'birth_date' => ['birth_date', 'birthdate', 'birth date', 'dob', 'date of birth', 'date_of_birth', 'born', 'born date', 'calving date'],
            'sex' => ['sex', 'gender', 'm/f', 'male/female'],
            'dam_id' => ['dam_id', 'damid', 'dam id', 'dam', 'mother', 'mother id', 'mother_id', 'cow id', 'cow_id', 'dam tag', 'dam_tag'],
            'birth_weight' => ['birth_weight', 'birthweight', 'birth weight', 'weight', 'weight at birth', 'bw'],
            'health_status' => ['health_status', 'health status', 'health', 'status', 'condition'],
            'breed' => ['breed', 'breed type', 'breed_type'],
            'batch_id' => ['batch_id', 'batch id', 'batch', 'group', 'pen'],
            'pen_location' => ['pen_location', 'pen location', 'pen', 'location', 'pen number', 'pen_number']
        ];

        $mappedColumns = [];
        
        foreach ($headers as $index => $header) {
            $normalizedHeader = $this->normalizeColumnName($header);
            
            foreach ($columnMappings as $dbField => $variations) {
                $normalizedVariations = array_map([$this, 'normalizeColumnName'], $variations);
                
                if (in_array($normalizedHeader, $normalizedVariations)) {
                    $mappedColumns[$index] = $dbField;
                    break;
                }
            }
            
            if (!isset($mappedColumns[$index])) {
                $mappedColumns[$index] = $normalizedHeader;
            }
        }
        
        return $mappedColumns;
    }

    private function normalizeColumnName($columnName) {
        return strtolower(trim(str_replace(['_', '-', '.'], ' ', $columnName)));
    }

    private function validateRequiredFields($mappedColumns) {
        $requiredFields = ['calf_id', 'birth_date', 'sex'];
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            if (!in_array($field, $mappedColumns)) {
                $missingFields[] = $field;
            }
        }
        
        return [
            'valid' => empty($missingFields),
            'missing_fields' => $missingFields
        ];
    }

    private function validateImportRow($rowData, $rowNumber) {
        $errors = [];
        
        if (empty($rowData['calf_id'])) {
            $errors[] = "Row $rowNumber: Calf ID is required";
        }
        
        if (empty($rowData['birth_date'])) {
            $errors[] = "Row $rowNumber: Birth date is required";
        } else {
            $parsedDate = $this->parseDate($rowData['birth_date']);
            if (!$parsedDate) {
                $errors[] = "Row $rowNumber: Invalid birth date format: {$rowData['birth_date']}";
            } else {
                $rowData['birth_date'] = $parsedDate;
            }
        }
        
        if (empty($rowData['sex'])) {
            $errors[] = "Row $rowNumber: Sex is required";
        } else {
            $sex = $this->parseSex($rowData['sex']);
            if (!$sex) {
                $errors[] = "Row $rowNumber: Invalid sex value: {$rowData['sex']}. Must be male/female or M/F";
            } else {
                $rowData['sex'] = $sex;
            }
        }
        
        if (!empty($rowData['birth_weight'])) {
            if (!is_numeric($rowData['birth_weight']) || $rowData['birth_weight'] < 0) {
                $errors[] = "Row $rowNumber: Invalid birth weight: {$rowData['birth_weight']}";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $rowData
        ];
    }

    private function parseDate($dateString) {
        $dateString = trim($dateString);
        
        $formats = [
            'd-m-Y', 'd/m/Y', 'Y-m-d', 'm/d/Y', 'd.m.Y', 'Y/m/d',
        ];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateString);
            if ($date && $date->format($format) === $dateString) {
                return $date->format('Y-m-d');
            }
        }
        
        $timestamp = strtotime($dateString);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        
        return false;
    }

    private function parseSex($sexString) {
        $sexString = strtolower(trim($sexString));
        
        $maleForms = ['m', 'male', 'bull', 'boy'];
        $femaleForms = ['f', 'female', 'heifer', 'girl', 'cow'];
        
        if (in_array($sexString, $maleForms)) {
            return 'male';
        }
        
        if (in_array($sexString, $femaleForms)) {
            return 'female';
        }
        
        return false;
    }

    private function parseHealthStatus($healthString) {
        if (empty($healthString)) {
            return 'healthy';
        }
        
        $healthString = strtolower(trim($healthString));
        
        $healthyForms = ['healthy', 'good', 'ok', 'normal', 'fine'];
        $attentionForms = ['needs attention', 'attention', 'watch', 'monitor', 'caution', 'needs_attention'];
        $sickForms = ['sick', 'ill', 'poor', 'bad', 'treatment'];
        
        if (in_array($healthString, $healthyForms)) {
            return 'healthy';
        }
        
        if (in_array($healthString, $attentionForms)) {
            return 'needs_attention';
        }
        
        if (in_array($healthString, $sickForms)) {
            return 'sick';
        }
        
        return 'healthy';
    }

    private function detectDelimiter($line) {
        $delimiters = [',', ';', '|', "\t"];
        $delimiterCounts = [];
        
        foreach ($delimiters as $delimiter) {
            $delimiterCounts[$delimiter] = substr_count($line, $delimiter);
        }
        
        arsort($delimiterCounts);
        return key($delimiterCounts);
    }

    private function showImportForm() {
        $supportedFormats = ['csv', 'txt'];
        require_once BASE_PATH . '/app/core/ModernNavbar.php';
        $navbar = new ModernNavbar();
        include BASE_PATH . '/app/modules/Calves/views/import.php';
    }

    private function scheduleEventsForCalf($calfId, $birthDate) {
        $events = $this->db->fetchAll("SELECT * FROM events WHERE is_active = 1");
        
        foreach ($events as $event) {
            $dueDate = date('Y-m-d', strtotime($birthDate . ' + ' . $event['age_start'] . ' days'));
            
            $this->db->query("
                INSERT INTO calf_events (calf_id, event_id, due_date, status, created_at)
                VALUES (?, ?, ?, 'pending', NOW())",
                [$calfId, $event['id'], $dueDate]
            );
        }
    }

    private function generateCalfId() {
        $prefix = 'CALF-' . date('Y') . '-';
        $lastCalf = $this->db->fetch("
            SELECT calf_id FROM calves 
            WHERE calf_id LIKE ? 
            AND status != 'deleted'
            ORDER BY id DESC LIMIT 1",
            [$prefix . '%']
        );

        if ($lastCalf) {
            $lastNumber = intval(str_replace($prefix, '', $lastCalf['calf_id']));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
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
        try {
            $this->db->query("
                INSERT INTO activity_logs (user_id, activity_type, description, calf_id, treatment_plan_id, ip_address, created_at)
                VALUES (?, 'calf_management', ?, ?, ?, ?, NOW())",
                [
                    $_SESSION['user_id'],
                    $description,
                    $calfId,
                    $treatmentPlanId,
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]
            );
        } catch (Exception $e) {
            error_log("Activity log error: " . $e->getMessage());
        }
    }

    // ==================== EXPORT METHOD ====================

    public function export() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        // Only export active calves (exclude deleted)
        $calves = $this->db->fetchAll("
            SELECT c.calf_id, c.birth_date, c.sex, c.dam_id, c.birth_weight, 
                   c.health_status, c.status, c.breed, c.pen_location, b.name as batch_name,
                   DATEDIFF(NOW(), c.birth_date) as age_days
            FROM calves c
            LEFT JOIN batches b ON c.batch_id = b.id
            WHERE c.status != 'deleted'
            ORDER BY c.calf_id ASC
        ");

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="calves_export_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Calf ID', 'Birth Date', 'Age (Days)', 'Sex', 'Dam ID', 'Birth Weight', 'Breed', 'Health Status', 'Status', 'Batch', 'Pen Location']);

        foreach ($calves as $calf) {
            fputcsv($output, [
                $calf['calf_id'],
                $calf['birth_date'],
                $calf['age_days'],
                $calf['sex'],
                $calf['dam_id'] ?? '',
                $calf['birth_weight'] ?? '',
                $calf['breed'] ?? '',
                $calf['health_status'],
                $calf['status'],
                $calf['batch_name'] ?? 'No Batch',
                $calf['pen_location'] ?? ''
            ]);
        }

        fclose($output);
        exit;
    }

    // ==================== BATCH MANAGEMENT ====================

    public function batch($id = null) {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        if (!$id) {
            header('Location: /public/calves');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_id'])) {
            try {
                $batchId = $_POST['batch_id'] ?: null;

                $this->db->query("UPDATE calves SET batch_id = ?, updated_at = NOW() WHERE id = ?", [$batchId, $id]);

                $_SESSION['success_message'] = "Batch updated successfully!";
                header('Location: /public/calves/passport/' . $id);
                exit;

            } catch (Exception $e) {
                $_SESSION['error_message'] = "Error: " . $e->getMessage();
            }
        }

        $calf = $this->db->fetch("SELECT * FROM calves WHERE id = ? AND status != 'deleted'", [$id]);
        if (!$calf) {
            $_SESSION['error_message'] = "Calf not found";
            header('Location: /public/calves');
            exit;
        }

        $batches = $this->db->fetchAll("SELECT * FROM batches WHERE is_active = 1 ORDER BY name ASC");
        include BASE_PATH . '/app/modules/Calves/views/batch_selection.php';
    }

    public function bulkBatch() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_batch'])) {
            try {
                $calfIds = $_POST['calf_ids'] ?? [];
                $batchId = $_POST['batch_id'] ?: null;

                if (empty($calfIds) || !is_array($calfIds)) {
                    throw new Exception("No calves selected");
                }

                $placeholders = str_repeat('?,', count($calfIds) - 1) . '?';
                
                $this->db->query(
                    "UPDATE calves SET batch_id = ?, updated_at = NOW() WHERE id IN ($placeholders) AND status != 'deleted'",
                    array_merge([$batchId], $calfIds)
                );

                $batchName = 'No Batch';
                if ($batchId) {
                    $batch = $this->db->fetch("SELECT name FROM batches WHERE id = ?", [$batchId]);
                    $batchName = $batch['name'] ?? 'Unknown Batch';
                }

                $_SESSION['success_message'] = count($calfIds) . " calves moved to $batchName";

            } catch (Exception $e) {
                $_SESSION['error_message'] = "Error: " . $e->getMessage();
            }

            header('Location: /public/calves');
            exit;
        }

        $calfIds = $_GET['ids'] ?? '';
        $ids = array_filter(explode(',', $calfIds), 'is_numeric');
        
        if (empty($ids)) {
            $_SESSION['error_message'] = "No calves selected";
            header('Location: /public/calves');
            exit;
        }

        $batches = $this->db->fetchAll("SELECT * FROM batches WHERE is_active = 1 ORDER BY name ASC");
        include BASE_PATH . '/app/modules/Calves/views/bulk_batch.php';
    }

    public function bulkHealth() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_health'])) {
            try {
                $calfIds = $_POST['calf_ids'] ?? [];
                $healthStatus = $_POST['health_status'];

                if (empty($calfIds) || !is_array($calfIds)) {
                    throw new Exception("No calves selected");
                }

                if (!in_array($healthStatus, ['healthy', 'needs_attention', 'sick'])) {
                    throw new Exception("Invalid health status");
                }

                $placeholders = str_repeat('?,', count($calfIds) - 1) . '?';
                
                $this->db->query(
                    "UPDATE calves SET health_status = ?, updated_at = NOW() WHERE id IN ($placeholders) AND status != 'deleted'",
                    array_merge([$healthStatus], $calfIds)
                );

                $_SESSION['success_message'] = count($calfIds) . " calves updated to " . str_replace('_', ' ', $healthStatus);

            } catch (Exception $e) {
                $_SESSION['error_message'] = "Error: " . $e->getMessage();
            }

            header('Location: /public/calves');
            exit;
        }

        $calfIds = $_GET['ids'] ?? '';
        $ids = array_filter(explode(',', $calfIds), 'is_numeric');
        
        if (empty($ids)) {
            $_SESSION['error_message'] = "No calves selected";
            header('Location: /public/calves');
            exit;
        }

        include BASE_PATH . '/app/modules/Calves/views/bulk_health.php';
    }
}
?>