<?php
/**
 * Import Handler
 * Main logic for handling bulk calf imports
 *
 * Fixes:
 * - Duplicate checks now ignore soft-deleted calves (status = 'deleted')
 * - Normalizes calf_id when checking for duplicates to reduce false positives
 * - Keeps previous insert/update flow unchanged
 *
 * Usage:
 * $handler = new ImportHandler($db, $userId);
 * $handler->processRow($row, $rowNumber, $duplicateAction);
 */
class ImportHandler {
    private $db;
    private $userId;
    private $columnMapper;
    private $fileParser;
    private $validator;

    private $results = array(
        'total_rows' => 0,
        'successful' => 0,
        'failed' => 0,
        'duplicates' => 0,
        'skipped' => 0,
        'updated' => 0,
        'errors' => array(),
        'successful_imports' => array(),
        'duplicate_list' => array(),
        'failed_list' => array()
    );

    public function __construct($db, $userId) {
        $this->db = $db;
        $this->userId = $userId;

        // Optional import helpers (column mapping, parser, validation)
        require_once BASE_PATH . '/app/modules/Calves/import/ColumnMapper.php';
        require_once BASE_PATH . '/app/modules/Calves/import/FileParser.php';
        require_once BASE_PATH . '/app/modules/Calves/import/ValidationRules.php';

        $this->columnMapper = new ColumnMapper();
        $this->fileParser = new FileParser();
        $this->validator = new ValidationRules();
    }

    /**
     * Return results summary
     */
    public function getResults() {
        return $this->results;
    }

    /**
     * Process a single parsed row (public entrypoint)
     *
     * $row: array of raw values (indexed by column)
     * $rowNumber: integer
     * $duplicateAction: 'skip'|'update' (from UI)
     */
    public function processRow(array $row, $rowNumber, $duplicateAction = 'skip') {
        $this->results['total_rows']++;

        try {
            // Map & validate the row (throws on severe problems)
            $columnMap = $this->columnMapper->detect($row); // may return a mapping or be supplied externally
            $calfData = $this->extractCalfData($row, $columnMap);

            // Normalize calf_id for consistent matching (trim + uppercase)
            $normalizedCalfId = strtoupper(trim($calfData['calf_id']));

            // CRITICAL CHANGE:
            // Only check for ACTIVE calves (ignore soft-deleted rows)
            $existing = $this->db->fetch(
                "SELECT id, calf_id FROM calves WHERE calf_id = ? AND status != 'deleted' LIMIT 1",
                array($normalizedCalfId)
            );

            if ($existing) {
                $this->handleDuplicate($existing, $calfData, $rowNumber, $duplicateAction);
            } else {
                $this->insertCalf($calfData, $rowNumber);
            }

        } catch (Exception $e) {
            $this->results['failed']++;
            $this->results['failed_list'][] = array(
                'row' => $rowNumber,
                'calf_id' => isset($calfData['calf_id']) ? $calfData['calf_id'] : 'Unknown',
                'errors' => array($e->getMessage())
            );
        }
    }

    /**
     * Map raw row into calf data fields expected by DB
     */
    private function extractCalfData($row, $columnMap) {
        $calfData = array();

        foreach ($columnMap as $colIndex => $dbField) {
            if (isset($row[$colIndex]) && trim($row[$colIndex]) !== '') {
                $calfData[$dbField] = trim($row[$colIndex]);
            }
        }

        // Defaults and normalization
        if (!isset($calfData['health_status'])) {
            $calfData['health_status'] = 'healthy';
        } else {
            $calfData['health_status'] = $this->validator->parseHealthStatus($calfData['health_status']);
        }

        if (!isset($calfData['status'])) {
            $calfData['status'] = 'active';
        }

        if (isset($calfData['calf_id'])) {
            $calfData['calf_id'] = strtoupper(trim($calfData['calf_id']));
        }

        return $calfData;
    }

    /**
     * Handle duplicate row according to user's choice
     */
    private function handleDuplicate($existing, $calfData, $rowNumber, $duplicateAction) {
        switch ($duplicateAction) {
            case 'skip':
                $this->results['duplicates']++;
                $this->results['duplicate_list'][] = array(
                    'row' => $rowNumber,
                    'calf_id' => $calfData['calf_id'],
                    'action' => 'Skipped'
                );
                break;

            case 'update':
                // Update existing calf record (preserve created_at but update fields)
                $this->updateCalf($existing['id'], $calfData, $rowNumber);
                $this->results['updated']++;
                $this->results['duplicate_list'][] = array(
                    'row' => $rowNumber,
                    'calf_id' => $calfData['calf_id'],
                    'action' => 'Updated'
                );
                break;

            default:
                // Unknown action - treat as skip
                $this->results['skipped']++;
                break;
        }
    }

    /**
     * Insert a new calf
     */
    private function insertCalf($calfData, $rowNumber) {
        try {
            $this->db->query("
                INSERT INTO calves (
                    calf_id, birth_date, sex, dam_id, birth_weight,
                    health_status, status, batch_id, breed, pen_location,
                    created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ", array(
                $calfData['calf_id'],
                isset($calfData['birth_date']) ? $calfData['birth_date'] : null,
                isset($calfData['sex']) ? $calfData['sex'] : null,
                isset($calfData['dam_id']) ? $calfData['dam_id'] : null,
                isset($calfData['birth_weight']) ? $calfData['birth_weight'] : null,
                isset($calfData['health_status']) ? $calfData['health_status'] : 'healthy',
                isset($calfData['status']) ? $calfData['status'] : 'active',
                isset($calfData['batch_id']) ? $calfData['batch_id'] : null,
                isset($calfData['breed']) ? $calfData['breed'] : null,
                isset($calfData['pen_location']) ? $calfData['pen_location'] : null,
                $this->userId
            ));

            // If the DB wrapper supports lastInsertId() use it to schedule events
            if (method_exists($this->db, 'lastInsertId')) {
                $newCalfId = $this->db->lastInsertId();
                if ($newCalfId) {
                    $this->scheduleEventsForCalf($newCalfId, isset($calfData['birth_date']) ? $calfData['birth_date'] : null);
                }
            }

            $this->results['successful']++;
            $this->results['successful_imports'][] = array(
                'row' => $rowNumber,
                'calf_id' => $calfData['calf_id']
            );

        } catch (Exception $e) {
            $this->results['failed']++;
            $this->results['failed_list'][] = array(
                'row' => $rowNumber,
                'calf_id' => $calfData['calf_id'],
                'errors' => array("Database error: " . $e->getMessage())
            );
        }
    }

    /**
     * Update an existing calf
     */
    private function updateCalf($calfDbId, $calfData, $rowNumber) {
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
            ", array(
                isset($calfData['birth_date']) ? $calfData['birth_date'] : null,
                isset($calfData['sex']) ? $calfData['sex'] : null,
                isset($calfData['dam_id']) ? $calfData['dam_id'] : null,
                isset($calfData['birth_weight']) ? $calfData['birth_weight'] : null,
                isset($calfData['health_status']) ? $calfData['health_status'] : 'healthy',
                isset($calfData['breed']) ? $calfData['breed'] : null,
                isset($calfData['pen_location']) ? $calfData['pen_location'] : null,
                $calfDbId
            ));

            $this->results['successful']++;
            $this->results['successful_imports'][] = array(
                'row' => $rowNumber,
                'calf_id' => $calfData['calf_id']
            );

        } catch (Exception $e) {
            $this->results['failed']++;
            $this->results['failed_list'][] = array(
                'row' => $rowNumber,
                'calf_id' => $calfData['calf_id'],
                'errors' => array("Update error: " . $e->getMessage())
            );
        }
    }

    /**
     * Schedule events for a calf (placeholder - keep if your app schedules tasks)
     * Implement scheduling logic consistent with the rest of the app.
     */
    private function scheduleEventsForCalf($calfId, $birthDate = null) {
        // This method intentionally minimal — your app may have complex scheduling logic.
        // If you have a TasksModel or scheduler, call into that here.
        try {
            if (empty($calfId) || empty($birthDate)) return;
            // Example: require_once BASE_PATH . '/app/modules/Tasks/model.php'; (if needed)
            // $tasksModel = new TasksModel();
            // $tasksModel->createInitialEventsForCalf($calfId, $birthDate);
        } catch (Exception $e) {
            // Don't fail the import on scheduling errors; log for admin review
            error_log("scheduleEventsForCalf error: " . $e->getMessage());
        }
    }
}