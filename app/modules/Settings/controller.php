<?php
namespace App\Modules\Settings;

use App\Core\Controller;

class SettingsController extends Controller
{
    private $table = 'settings';

    public function __construct()
    {
        parent::__construct();
        
        // The parent Controller already initializes $this->db
        if ($this->db === null) {
            $this->db = new \Database();
        }
        
        // Check if user is authenticated and is admin
        if (empty($_SESSION['user_id']) || (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin')) {
            $this->redirect('/login');
            exit;
        }
    }

    /**
     * Display settings dashboard
     */
    public function index()
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $data = [
            'title' => 'Settings Control Panel',
            'settings' => $this->getAllSettings($limit, $offset),
            'categories' => $this->getCategories(),
            'stats' => $this->getStats(),
            'pagination' => $this->getPagination($page, $limit),
            'public_access_status' => $this->getPublicAccessStatus()
        ];
        
        $this->render('settings/index', $data);
    }

    /**
     * Get all settings with pagination
     */
    private function getAllSettings($limit = 50, $offset = 0)
    {
        $query = "SELECT * FROM {$this->table} 
                  WHERE deleted_at IS NULL
                  ORDER BY category ASC, setting_order ASC, setting_key ASC 
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->query($query, [
            'limit' => $limit,
            'offset' => $offset
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Get pagination data
     */
    private function getPagination($currentPage, $limit)
    {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM {$this->table} WHERE deleted_at IS NULL");
        $result = $stmt->fetchAll();
        $totalItems = $result[0]['total'] ?? 0;
        $totalPages = ceil($totalItems / $limit);

        return [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'total_items' => $totalItems,
            'has_previous' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages
        ];
    }

    /**
     * Get unique categories
     */
    private function getCategories()
    {
        $query = "SELECT DISTINCT category FROM {$this->table} 
                  WHERE category IS NOT NULL AND category != '' 
                  AND deleted_at IS NULL
                  ORDER BY category ASC";
        
        $stmt = $this->db->query($query);
        return $stmt->fetchAll();
    }

    /**
     * Get dashboard statistics
     */
    private function getStats()
    {
        $stats = [];
        
        // Total settings
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM {$this->table} WHERE deleted_at IS NULL");
        $result = $stmt->fetchAll();
        $stats['total'] = $result[0]['total'] ?? 0;
        
        // Active settings
        $stmt = $this->db->query("SELECT COUNT(*) as active FROM {$this->table} WHERE is_active = 1 AND deleted_at IS NULL");
        $result = $stmt->fetchAll();
        $stats['active'] = $result[0]['active'] ?? 0;
        
        // Categories count
        $stmt = $this->db->query("SELECT COUNT(DISTINCT category) as categories FROM {$this->table} WHERE deleted_at IS NULL");
        $result = $stmt->fetchAll();
        $stats['categories'] = $result[0]['categories'] ?? 0;
        
        // Recently modified (last 7 days)
        $stmt = $this->db->query("SELECT COUNT(*) as recent FROM {$this->table} 
                                   WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                                   AND deleted_at IS NULL");
        $result = $stmt->fetchAll();
        $stats['recent'] = $result[0]['recent'] ?? 0;
        
        return $stats;
    }

    /**
     * Show create form
     */
    public function create()
    {
        $data = [
            'title' => 'Add New Setting',
            'categories' => $this->getCategories(),
            'setting_types' => $this->getSettingTypes()
        ];
        
        $this->render('settings/create', $data);
    }

    /**
     * Get available setting types
     */
    private function getSettingTypes()
    {
        return [
            'text' => 'Text',
            'textarea' => 'Text Area',
            'number' => 'Number',
            'boolean' => 'Boolean',
            'select' => 'Select',
            'json' => 'JSON',
            'password' => 'Password',
            'email' => 'Email',
            'url' => 'URL'
        ];
    }

    /**
     * Store new setting
     */
    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/settings');
            return;
        }

        $data = $_POST;
        
        // Validate required fields
        $errors = $this->validateSetting($data);
        
        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode('<br>', $errors);
            $this->redirect('/settings/create');
            return;
        }

        // Check if setting key already exists
        $stmt = $this->db->query(
            "SELECT id FROM {$this->table} WHERE setting_key = :key AND deleted_at IS NULL",
            ['key' => $data['setting_key']]
        );
        $existing = $stmt->fetchAll();

        if (!empty($existing)) {
            $_SESSION['flash_error'] = 'Setting key already exists!';
            $this->redirect('/settings/create');
            return;
        }

        // Prepare data for insertion
        $insertData = [
            'setting_key' => $data['setting_key'],
            'setting_value' => $data['setting_value'] ?? '',
            'setting_type' => $data['setting_type'] ?? 'text',
            'category' => $data['category'] ?? 'general',
            'description' => $data['description'] ?? '',
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'is_public' => isset($data['is_public']) ? 1 : 0,
            'setting_order' => (int) ($data['setting_order'] ?? 0),
            'validation_rules' => $data['validation_rules'] ?? null,
            'options' => $data['options'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $query = "INSERT INTO {$this->table} 
                  (setting_key, setting_value, setting_type, category, description, 
                   is_active, is_public, setting_order, validation_rules, options, created_at, updated_at)
                  VALUES 
                  (:setting_key, :setting_value, :setting_type, :category, :description,
                   :is_active, :is_public, :setting_order, :validation_rules, :options, :created_at, :updated_at)";

        $stmt = $this->db->query($query, $insertData);
        if ($stmt) {
            $_SESSION['flash_success'] = 'Setting created successfully!';
            $this->redirect('/settings');
        } else {
            $_SESSION['flash_error'] = 'Failed to create setting!';
            $this->redirect('/settings/create');
        }
    }

    /**
     * Show edit form
     */
    public function edit($id)
    {
        $stmt = $this->db->query(
            "SELECT * FROM {$this->table} WHERE id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );
        $setting = $stmt->fetchAll();

        if (empty($setting)) {
            $_SESSION['flash_error'] = 'Setting not found!';
            $this->redirect('/settings');
            return;
        }

        $data = [
            'title' => 'Edit Setting',
            'setting' => $setting[0],
            'categories' => $this->getCategories(),
            'setting_types' => $this->getSettingTypes()
        ];
        
        $this->render('settings/edit', $data);
    }

    /**
     * Update existing setting
     */
    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/settings');
            return;
        }

        $data = $_POST;
        
        // Validate required fields
        $errors = $this->validateSetting($data, $id);
        
        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode('<br>', $errors);
            $this->redirect('/settings/edit/' . $id);
            return;
        }

        // Check if setting key already exists (excluding current record)
        $stmt = $this->db->query(
            "SELECT id FROM {$this->table} WHERE setting_key = :key AND id != :id AND deleted_at IS NULL",
            ['key' => $data['setting_key'], 'id' => $id]
        );
        $existing = $stmt->fetchAll();

        if (!empty($existing)) {
            $_SESSION['flash_error'] = 'Setting key already exists!';
            $this->redirect('/settings/edit/' . $id);
            return;
        }

        // Prepare data for update
        $updateData = [
            'id' => $id,
            'setting_key' => $data['setting_key'],
            'setting_value' => $data['setting_value'] ?? '',
            'setting_type' => $data['setting_type'] ?? 'text',
            'category' => $data['category'] ?? 'general',
            'description' => $data['description'] ?? '',
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'is_public' => isset($data['is_public']) ? 1 : 0,
            'setting_order' => (int) ($data['setting_order'] ?? 0),
            'validation_rules' => $data['validation_rules'] ?? null,
            'options' => $data['options'] ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $query = "UPDATE {$this->table} SET 
                  setting_key = :setting_key,
                  setting_value = :setting_value,
                  setting_type = :setting_type,
                  category = :category,
                  description = :description,
                  is_active = :is_active,
                  is_public = :is_public,
                  setting_order = :setting_order,
                  validation_rules = :validation_rules,
                  options = :options,
                  updated_at = :updated_at
                  WHERE id = :id";

        $stmt = $this->db->query($query, $updateData);
        if ($stmt) {
            $_SESSION['flash_success'] = 'Setting updated successfully!';
            $this->redirect('/settings');
        } else {
            $_SESSION['flash_error'] = 'Failed to update setting!';
            $this->redirect('/settings/edit/' . $id);
        }
    }

    /**
     * Soft delete setting
     */
    public function delete($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request method']);
            return;
        }

        $query = "UPDATE {$this->table} SET deleted_at = :deleted_at WHERE id = :id";
        
        $stmt = $this->db->query($query, [
            'deleted_at' => date('Y-m-d H:i:s'),
            'id' => $id
        ]);
        
        if ($stmt) {
            $_SESSION['flash_success'] = 'Setting moved to trash!';
            $this->json(['success' => true, 'message' => 'Setting deleted successfully']);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to delete setting']);
        }
    }

    /**
     * Hard delete setting - PERMANENT deletion
     */
    public function hardDelete($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request method']);
            return;
        }

        // Get confirmation from request
        $confirmed = $_POST['confirmed'] ?? '';
        
        if ($confirmed !== 'yes') {
            $this->json([
                'success' => false, 
                'message' => 'Deletion not confirmed. This action is irreversible!'
            ]);
            return;
        }

        // Get setting details before deletion for logging
        $stmt = $this->db->query(
            "SELECT setting_key, category FROM {$this->table} WHERE id = :id",
            ['id' => $id]
        );
        $setting = $stmt->fetchAll();

        if (empty($setting)) {
            $this->json(['success' => false, 'message' => 'Setting not found']);
            return;
        }

        // Perform hard delete
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        
        $stmt = $this->db->query($query, ['id' => $id]);
        if ($stmt) {
            // Log the deletion
            $this->logDeletion($id, $setting[0]);
            
            $_SESSION['flash_success'] = 'Setting permanently deleted!';
            $this->json([
                'success' => true, 
                'message' => 'Setting permanently deleted'
            ]);
        } else {
            $this->json([
                'success' => false, 
                'message' => 'Failed to delete setting permanently'
            ]);
        }
    }

    /**
     * Show trash/deleted settings
     */
    public function trash()
    {
        $query = "SELECT * FROM {$this->table} 
                  WHERE deleted_at IS NOT NULL 
                  ORDER BY deleted_at DESC";
        
        $stmt = $this->db->query($query);
        $data = [
            'title' => 'Deleted Settings',
            'settings' => $stmt->fetchAll()
        ];
        
        $this->render('settings/trash', $data);
    }

    /**
     * Restore deleted setting
     */
    public function restore($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request method']);
            return;
        }

        $query = "UPDATE {$this->table} SET deleted_at = NULL WHERE id = :id";
        
        $stmt = $this->db->query($query, ['id' => $id]);
        if ($stmt) {
            $_SESSION['flash_success'] = 'Setting restored successfully!';
            $this->json(['success' => true, 'message' => 'Setting restored']);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to restore setting']);
        }
    }

    /**
     * Bulk operations
     */
    public function bulk()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/settings');
            return;
        }

        $action = $_POST['action'] ?? '';
        $ids = $_POST['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            $_SESSION['flash_error'] = 'No settings selected!';
            $this->redirect('/settings');
            return;
        }

        // Convert IDs to integers for safety
        $ids = array_map('intval', $ids);
        $placeholders = implode(',', $ids);
        $success = false;

        switch ($action) {
            case 'activate':
                $query = "UPDATE {$this->table} SET is_active = 1 WHERE id IN ($placeholders) AND deleted_at IS NULL";
                $stmt = $this->db->query($query);
                $success = ($stmt !== false);
                $message = 'Settings activated';
                break;

            case 'deactivate':
                $query = "UPDATE {$this->table} SET is_active = 0 WHERE id IN ($placeholders) AND deleted_at IS NULL";
                $stmt = $this->db->query($query);
                $success = ($stmt !== false);
                $message = 'Settings deactivated';
                break;

            case 'delete':
                $query = "UPDATE {$this->table} SET deleted_at = :deleted_at WHERE id IN ($placeholders)";
                $stmt = $this->db->query($query, ['deleted_at' => date('Y-m-d H:i:s')]);
                $success = ($stmt !== false);
                $message = 'Settings moved to trash';
                break;

            case 'hard_delete':
                // Require additional confirmation for bulk hard delete
                $confirmed = $_POST['confirmed'] ?? '';
                if ($confirmed !== 'yes') {
                    $_SESSION['flash_error'] = 'Bulk hard delete requires confirmation!';
                    $this->redirect('/settings');
                    return;
                }
                
                $query = "DELETE FROM {$this->table} WHERE id IN ($placeholders)";
                $stmt = $this->db->query($query);
                $success = ($stmt !== false);
                $message = 'Settings permanently deleted';
                break;

            default:
                $_SESSION['flash_error'] = 'Invalid bulk action!';
                $this->redirect('/settings');
                return;
        }

        if ($success) {
            $_SESSION['flash_success'] = $message . ' successfully!';
        } else {
            $_SESSION['flash_error'] = 'Bulk operation failed!';
        }

        $this->redirect('/settings');
    }

    /**
     * Search settings
     */
    public function search()
    {
        $query = $_GET['q'] ?? '';
        $category = $_GET['category'] ?? '';

        if (empty($query) && empty($category)) {
            $this->redirect('/settings');
            return;
        }

        $sql = "SELECT * FROM {$this->table} WHERE deleted_at IS NULL";
        $params = [];

        if (!empty($query)) {
            $sql .= " AND (setting_key LIKE :query OR setting_value LIKE :query OR description LIKE :query)";
            $params['query'] = "%{$query}%";
        }

        if (!empty($category)) {
            $sql .= " AND category = :category";
            $params['category'] = $category;
        }

        $sql .= " ORDER BY category ASC, setting_key ASC";

        $stmt = $this->db->query($sql, $params);
        $data = [
            'title' => 'Search Results',
            'settings' => $stmt->fetchAll(),
            'query' => $query,
            'category' => $category,
            'categories' => $this->getCategories()
        ];

        $this->render('settings/search', $data);
    }

    /**
     * Export settings as JSON
     */
    public function export()
    {
        $stmt = $this->db->query("SELECT * FROM {$this->table} WHERE deleted_at IS NULL");
        $settings = $stmt->fetchAll();
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="settings_' . date('Y-m-d_H-i-s') . '.json"');
        
        echo json_encode($settings, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Import settings from JSON
     */
    public function import()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
            $_SESSION['flash_error'] = 'No file uploaded!';
            $this->redirect('/settings');
            return;
        }

        $file = $_FILES['file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash_error'] = 'File upload failed!';
            $this->redirect('/settings');
            return;
        }
        
        // Check file type
        $fileType = mime_content_type($file['tmp_name']);
        if (!in_array($fileType, ['application/json', 'text/plain'])) {
            $_SESSION['flash_error'] = 'Invalid file type! Please upload a JSON file.';
            $this->redirect('/settings');
            return;
        }
        
        $content = file_get_contents($file['tmp_name']);
        $settings = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $_SESSION['flash_error'] = 'Invalid JSON file!';
            $this->redirect('/settings');
            return;
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($settings as $index => $setting) {
            // Validate required fields
            if (empty($setting['setting_key'])) {
                $errors[] = "Row {$index}: Missing setting_key";
                continue;
            }

            // Check if setting already exists
            $stmt = $this->db->query(
                "SELECT id FROM {$this->table} WHERE setting_key = :key AND deleted_at IS NULL",
                ['key' => $setting['setting_key']]
            );
            $exists = $stmt->fetchAll();

            if (!empty($exists)) {
                $skipped++;
                continue;
            }

            // Import setting
            $insertData = [
                'setting_key' => $setting['setting_key'],
                'setting_value' => $setting['setting_value'] ?? '',
                'setting_type' => $setting['setting_type'] ?? 'text',
                'category' => $setting['category'] ?? 'general',
                'description' => $setting['description'] ?? '',
                'is_active' => $setting['is_active'] ?? 1,
                'is_public' => $setting['is_public'] ?? 0,
                'setting_order' => $setting['setting_order'] ?? 0,
                'validation_rules' => $setting['validation_rules'] ?? null,
                'options' => $setting['options'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $query = "INSERT INTO {$this->table} 
                      (setting_key, setting_value, setting_type, category, description,
                       is_active, is_public, setting_order, validation_rules, options, created_at, updated_at)
                      VALUES 
                      (:setting_key, :setting_value, :setting_type, :category, :description,
                       :is_active, :is_public, :setting_order, :validation_rules, :options, :created_at, :updated_at)";

            $stmt = $this->db->query($query, $insertData);
            if ($stmt) {
                $imported++;
            } else {
                $errors[] = "Row {$index}: Failed to import setting";
            }
        }

        $message = "Imported {$imported} settings. Skipped {$skipped} duplicates.";
        if (!empty($errors)) {
            $message .= " Errors: " . implode(', ', array_slice($errors, 0, 5));
        }

        $_SESSION['flash_' . ($errors ? 'warning' : 'success')] = $message;
        $this->redirect('/settings');
    }

    /**
     * Validate setting data
     */
    private function validateSetting($data, $id = null)
    {
        $errors = [];

        if (empty($data['setting_key'])) {
            $errors[] = 'Setting key is required';
        } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $data['setting_key'])) {
            $errors[] = 'Setting key can only contain letters, numbers, underscores, dots, and hyphens';
        }

        if (empty($data['setting_type'])) {
            $errors[] = 'Setting type is required';
        }

        if (empty($data['category'])) {
            $errors[] = 'Category is required';
        }

        return $errors;
    }

    /**
     * Log deletion for audit trail
     */
    private function logDeletion($id, $setting)
    {
        $logFile = __DIR__ . '/../../../logs/settings_deletions.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logEntry = sprintf(
            "[%s] ID: %d | Key: %s | Category: %s | Deleted by: %s\n",
            date('Y-m-d H:i:s'),
            $id,
            $setting['setting_key'],
            $setting['category'],
            $_SESSION['user_email'] ?? 'Unknown'
        );
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Toggle Public Access
     */
    public function togglePublicAccess()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request method']);
            return;
        }

        $enable = ($_POST['enable'] ?? 'false') === 'true';
        $publicAccessFile = __DIR__ . '/../../../storage/public_access.json';
        $storageDir = dirname($publicAccessFile);
        
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }
        
        $accessData = [
            'enabled' => $enable,
            'enabled_at' => $enable ? date('Y-m-d H:i:s') : null,
            'enabled_by' => $_SESSION['user_email'] ?? 'Unknown',
            'expires_at' => $enable ? date('Y-m-d H:i:s', strtotime('+24 hours')) : null
        ];
        
        if (file_put_contents($publicAccessFile, json_encode($accessData, JSON_PRETTY_PRINT))) {
            $message = $enable 
                ? 'Public access enabled successfully. It will auto-disable after 24 hours.' 
                : 'Public access disabled successfully.';
            
            $_SESSION['flash_success'] = $message;
            $this->json([
                'success' => true, 
                'message' => $message,
                'data' => $accessData
            ]);
        } else {
            $_SESSION['flash_error'] = 'Failed to update public access settings!';
            $this->json([
                'success' => false, 
                'message' => 'Failed to update public access settings'
            ]);
        }
    }

    /**
     * Check if public access has expired
     */
    public function checkPublicAccessExpiration()
    {
        $publicAccessFile = __DIR__ . '/../../../storage/public_access.json';
        
        if (!file_exists($publicAccessFile)) {
            return false;
        }
        
        $accessData = json_decode(file_get_contents($publicAccessFile), true);
        
        if (!$accessData || !isset($accessData['enabled']) || !$accessData['enabled']) {
            return false;
        }
        
        if (!isset($accessData['enabled_at'])) {
            return false;
        }
        
        $enabledTime = strtotime($accessData['enabled_at']);
        $currentTime = time();
        $hoursSinceEnabled = ($currentTime - $enabledTime) / 3600;
        
        if ($hoursSinceEnabled >= 24) {
            $accessData['enabled'] = false;
            $accessData['disabled_at'] = date('Y-m-d H:i:s');
            $accessData['disabled_reason'] = 'Auto-disabled after 24 hours';
            
            file_put_contents($publicAccessFile, json_encode($accessData, JSON_PRETTY_PRINT));
            return true;
        }
        
        return false;
    }

    /**
     * Get public access status
     */
    public function getPublicAccessStatus()
    {
        $publicAccessFile = __DIR__ . '/../../../storage/public_access.json';
        
        if (!file_exists($publicAccessFile)) {
            return [
                'enabled' => false,
                'message' => 'Public access is disabled'
            ];
        }
        
        $accessData = json_decode(file_get_contents($publicAccessFile), true);
        
        if (!$accessData || !isset($accessData['enabled'])) {
            return [
                'enabled' => false,
                'message' => 'Public access is disabled'
            ];
        }
        
        if ($accessData['enabled'] && isset($accessData['enabled_at'])) {
            $enabledTime = strtotime($accessData['enabled_at']);
            $currentTime = time();
            $hoursSinceEnabled = ($currentTime - $enabledTime) / 3600;
            $hoursRemaining = max(0, 24 - $hoursSinceEnabled);
            
            if ($hoursSinceEnabled >= 24) {
                return [
                    'enabled' => false,
                    'expired' => true,
                    'message' => 'Public access has expired (auto-disabled after 24 hours)'
                ];
            }
            
            return [
                'enabled' => true,
                'enabled_at' => $accessData['enabled_at'],
                'expires_at' => $accessData['expires_at'],
                'hours_remaining' => round($hoursRemaining, 1),
                'message' => "Public access is enabled. Will auto-disable in " . round($hoursRemaining, 1) . " hours"
            ];
        }
        
        return [
            'enabled' => $accessData['enabled'],
            'message' => $accessData['enabled'] ? 'Public access is enabled' : 'Public access is disabled'
        ];
    }

    /**
     * Quick toggle for setting status
     */
    public function quickToggle($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request method']);
            return;
        }

        $field = $_POST['field'] ?? 'is_active';
        $allowedFields = ['is_active', 'is_public'];
        
        if (!in_array($field, $allowedFields)) {
            $this->json(['success' => false, 'message' => 'Invalid field']);
            return;
        }

        $stmt = $this->db->query(
            "SELECT {$field} FROM {$this->table} WHERE id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );
        $current = $stmt->fetchAll();

        if (empty($current)) {
            $this->json(['success' => false, 'message' => 'Setting not found']);
            return;
        }

        $newValue = $current[0][$field] ? 0 : 1;

        $query = "UPDATE {$this->table} SET {$field} = :value, updated_at = :updated_at WHERE id = :id";
        
        $stmt = $this->db->query($query, [
            'value' => $newValue,
            'updated_at' => date('Y-m-d H:i:s'),
            'id' => $id
        ]);
        
        if ($stmt) {
            $this->json([
                'success' => true, 
                'message' => 'Setting updated successfully',
                'new_value' => $newValue
            ]);
        } else {
            $this->json(['success' => false, 'message' => 'Failed to update setting']);
        }
    }

    // Stub methods for routes
    public function updateRatio() { $this->redirect('/settings'); }
    public function updateAllowances() { $this->redirect('/settings'); }
    public function addAllowance() { $this->redirect('/settings'); }
    public function deleteAllowance() { $this->redirect('/settings'); }
    public function updateWeaningSettings() { $this->redirect('/settings'); }
    public function updateFeedingTimes() { $this->redirect('/settings'); }
    public function addEvent() { $this->redirect('/settings'); }
    public function updateEvent() { $this->redirect('/settings'); }
    public function deleteEvent() { $this->redirect('/settings'); }
    public function toggleEvent() { $this->redirect('/settings'); }
    public function hardDeleteManagement() { $this->redirect('/settings'); }
    public function permanentDelete() { $this->redirect('/settings'); }
}