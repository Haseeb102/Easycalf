-- Add breed and pen_location fields to calves table
ALTER TABLE calves pen_location VARCHAR(100) NULL AFTER breed;
-- Add calf_weights table for growth tracking (optional, for future use)
CREATE TABLE IF NOT EXISTS calf_weights (
id int(11) NOT NULL AUTO_INCREMENT,
calf_id int(11) NOT NULL,
weight_date date NOT NULL,
weight decimal(6,2) NOT NULL,
notes text NULL,
recorded_by int(11) NOT NULL,
created_at datetime DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (id),
FOREIGN KEY (calf_id) REFERENCES calves(id) ON DELETE CASCADE,
FOREIGN KEY (recorded_by) REFERENCES users(id),
INDEX idx_calf_weight (calf_id, weight_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

### **6. Migration Script - `public/migrate_calf_passport.php`**

<artifact identifier="calf-passport-migration" type="application/vnd.ant.code" language="php" title="Calf Passport Migration Script">
<?php
// migrate_calf_passport.php - Add new fields for enhanced calf passport
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../app/config/database.php';
require_once '../app/core/Database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Calf Passport Migration - EasyCalf</title>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; padding: 2rem; background: linear-gradient(135deg, #667eea, #764ba2); min-height: 100vh; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 2.5rem; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        h1 { color: #1E88E5; margin-bottom: 0.5rem; font-size: 2rem; }
        .subtitle { color: #666; margin-bottom: 2rem; }
        .success { color: #155724; background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0; border-left: 4px solid #28a745; }
        .error { color: #721c24; background: #f8d7da; padding: 1rem; border-radius: 8px; margin: 1rem 0; border-left: 4px solid #dc3545; }
        .info { color: #0c5460; background: #d1ecf1; padding: 1rem; border-radius: 8px; margin: 1rem 0; border-left: 4px solid #17a2b8; }
        .warning { color: #856404; background: #fff3cd; padding: 1rem; border-radius: 8px; margin: 1rem 0; border-left: 4px solid #ffc107; }
        .btn { display: inline-block; padding: 1rem 2rem; background: linear-gradient(135deg, #1E88E5, #A1C349); color: white; text-decoration: none; border-radius: 8px; margin: 0.5rem; font-weight: 600; transition: all 0.3s ease; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(30,136,229,0.3); }
        .feature-list { background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin: 1.5rem 0; }
        .feature-list h3 { margin-top: 0; color: #1E88E5; }
        .feature-list ul { margin: 0.5rem 0 0 1.5rem; line-height: 2; }
        .step { background: #e3f2fd; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🐮 Calf Passport System Enhancement</h1>
        <p class='subtitle'>Upgrading your calf management system with enhanced passport features</p>";

try {
    $db = new Database();
    
    echo "<div class='info'><strong>📋 Starting Migration...</strong></div>";
    
    // Check which columns need to be added
    $check_columns = $db->fetchAll("
        SELECT COLUMN_NAME 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'calves'
    ");
    
    $existing_columns = array_column($check_columns, 'COLUMN_NAME');
    $added_columns = [];
    
    // Add breed column
    if (!in_array('breed', $existing_columns)) {
        $db->query("ALTER TABLE calves ADD COLUMN breed VARCHAR(50) NULL AFTER birth_weight");
        $added_columns[] = 'breed';
        echo "<div class='success'>✅ Added column: <strong>breed</strong></div>";
    } else {
        echo "<div class='info'>ℹ️ Column already exists: breed</div>";
    }
    
    // Add pen_location column
    if (!in_array('pen_location', $existing_columns)) {
        $db->query("ALTER TABLE calves ADD COLUMN pen_location VARCHAR(100) NULL AFTER breed");
        $added_columns[] = 'pen_location';
        echo "<div class='success'>✅ Added column: <strong>pen_location</strong></div>";
    } else {
        echo "<div class='info'>ℹ️ Column already exists: pen_location</div>";
    }
    
    // Create calf_weights table for growth tracking
    $weights_table_exists = $db->fetch("SHOW TABLES LIKE 'calf_weights'");
    
    if (!$weights_table_exists) {
        $db->query("
            CREATE TABLE `calf_weights` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `calf_id` int(11) NOT NULL,
              `weight_date` date NOT NULL,
              `weight` decimal(6,2) NOT NULL,
              `notes` text NULL,
              `recorded_by` int(11) NOT NULL,
              `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              FOREIGN KEY (`calf_id`) REFERENCES `calves`(`id`) ON DELETE CASCADE,
              FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`),
              INDEX `idx_calf_weight` (`calf_id`, `weight_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "<div class='success'>✅ Created table: <strong>calf_weights</strong> (for growth tracking)</div>";
    } else {
        echo "<div class='info'>ℹ️ Table already exists: calf_weights</div>";
    }
    
    echo "<div class='success'>
            <h3>🎉 Migration Completed Successfully!</h3>
            <p><strong>Database Changes:</strong></p>
            <ul style='margin: 0.5rem 0 0 1.5rem;'>
                <li>Added breed field to calves table</li>
                <li>Added pen_location field to calves table</li>
                <li>Created calf_weights table for weight tracking</li>
            </ul>
          </div>";
    
    echo "<div class='feature-list'>
            <h3>✨ New Features Available</h3>
            <ul>
                <li><strong>Enhanced Calf Passport:</strong> Beautiful timeline view of all calf events</li>
                <li><strong>Bulk Delete:</strong> Remove multiple test or unwanted calves at once</li>
                <li><strong>Bulk Batch Assignment:</strong> Move multiple calves to a batch quickly</li>
                <li><strong>Bulk Health Update:</strong> Update health status for multiple calves</li>
                <li><strong>Individual Delete:</strong> Delete single calves with confirmation</li>
                <li><strong>Enhanced Details:</strong> Track breed, pen location, and more</li>
                <li><strong>Sticky Header:</strong> Calf info stays visible while scrolling timeline</li>
                <li><strong>Print Passport:</strong> Print-friendly passport layout</li>
                <li><strong>Visual Timeline:</strong> Color-coded events with completion status</li>
            </ul>
          </div>";
    
    echo "<div class='warning'>
            <h3>📖 How to Use New Features</h3>
            <div class='step'>
                <strong>1. Bulk Actions (Calves Page):</strong>
                <ul style='margin: 0.5rem 0 0 1.5rem;'>
                    <li>Check boxes next to calves you want to manage</li>
                    <li>Use 'Select All' to select all visible calves</li>
                    <li>Choose action: Assign to Batch, Update Health, or Delete</li>
                </ul>
            </div>
            
            <div class='step'>
                <strong>2. Individual Delete:</strong>
                <ul style='margin: 0.5rem 0 0 1.5rem;'>
                    <li>Click the 🗑️ Delete button next to any calf</li>
                    <li>Confirm deletion when prompted</li>
                    <li>Calf will be marked as deleted (soft delete)</li>
                </ul>
            </div>
            
            <div class='step'>
                <strong>3. Enhanced Passport:</strong>
                <ul style='margin: 0.5rem 0 0 1.5rem;'>
                    <li>Click any Calf ID to view their passport</li>
                    <li>See all details in sticky header at top</li>
                    <li>Scroll through complete life events timeline</li>
                    <li>Print passport using 🖨️ Print button</li>
                </ul>
            </div>
          </div>";
    
    echo "<div style='margin-top: 2rem; text-align: center;'>
            <a href='/public/calves' class='btn'>🐮 Go to Calves Management</a>
            <a href='/public/' class='btn' style='background: #43A047;'>🏠 Dashboard</a>
          </div>";
          
    echo "<div style='margin-top: 2rem; padding: 1rem; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;'>
            <p><strong>⚠️ Note:</strong> You can safely delete this migration file after running it once.</p>
            <p style='margin: 0.5rem 0 0 0; font-size: 0.9rem;'>File location: <code>public/migrate_calf_passport.php</code></p>
          </div>";

} catch (Exception $e) {
    echo "<div class='error'>
            <h3>❌ Migration Failed</h3>
            <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p style='margin-top: 1rem;'>Please check your database permissions and try again.</p>
            <p style='font-size: 0.9rem; color: #666; margin-top: 1rem;'>
                <strong>Debug Info:</strong><br>
                File: " . htmlspecialchars($e->getFile()) . "<br>
                Line: " . $e->getLine() . "
            </p>
          </div>";
}

echo "</div></body></html>";
?>
</artifact>

### **7. Updated Route in public/index.php**

Add this route after the existing calves routes:
```php
case 'migrate-calf-passport':
    require_once BASE_PATH . '/public/migrate_calf_passport.php';
    break;
ADD COLUMN breed VARCHAR(50) NULL AFTER birth_weight,
ADD COLUMNpen_location VARCHAR(100) NULL AFTER breed;
-- Add calf_weights table for growth tracking (optional, for future use)
CREATE TABLE IF NOT EXISTS calf_weights (
id int(11) NOT NULL AUTO_INCREMENT,
calf_id int(11) NOT NULL,
weight_date date NOT NULL,
weight decimal(6,2) NOT NULL,
notes text NULL,
recorded_by int(11) NOT NULL,
created_at datetime DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (id),
FOREIGN KEY (calf_id) REFERENCES calves(id) ON DELETE CASCADE,
FOREIGN KEY (recorded_by) REFERENCES users(id),
INDEX idx_calf_weight (calf_id, weight_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

### **6. Migration Script - `public/migrate_calf_passport.php`**

<artifact identifier="calf-passport-migration" type="application/vnd.ant.code" language="php" title="Calf Passport Migration Script">
<?php
// migrate_calf_passport.php - Add new fields for enhanced calf passport
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../app/config/database.php';
require_once '../app/core/Database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Calf Passport Migration - EasyCalf</title>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; padding: 2rem; background: linear-gradient(135deg, #667eea, #764ba2); min-height: 100vh; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 2.5rem; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        h1 { color: #1E88E5; margin-bottom: 0.5rem; font-size: 2rem; }
        .subtitle { color: #666; margin-bottom: 2rem; }
        .success { color: #155724; background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0; border-left: 4px solid #28a745; }
        .error { color: #721c24; background: #f8d7da; padding: 1rem; border-radius: 8px; margin: 1rem 0; border-left: 4px solid #dc3545; }
        .info { color: #0c5460; background: #d1ecf1; padding: 1rem; border-radius: 8px; margin: 1rem 0; border-left: 4px solid #17a2b8; }
        .warning { color: #856404; background: #fff3cd; padding: 1rem; border-radius: 8px; margin: 1rem 0; border-left: 4px solid #ffc107; }
        .btn { display: inline-block; padding: 1rem 2rem; background: linear-gradient(135deg, #1E88E5, #A1C349); color: white; text-decoration: none; border-radius: 8px; margin: 0.5rem; font-weight: 600; transition: all 0.3s ease; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(30,136,229,0.3); }
        .feature-list { background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin: 1.5rem 0; }
        .feature-list h3 { margin-top: 0; color: #1E88E5; }
        .feature-list ul { margin: 0.5rem 0 0 1.5rem; line-height: 2; }
        .step { background: #e3f2fd; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🐮 Calf Passport System Enhancement</h1>
        <p class='subtitle'>Upgrading your calf management system with enhanced passport features</p>";

try {
    $db = new Database();
    
    echo "<div class='info'><strong>📋 Starting Migration...</strong></div>";
    
    // Check which columns need to be added
    $check_columns = $db->fetchAll("
        SELECT COLUMN_NAME 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'calves'
    ");
    
    $existing_columns = array_column($check_columns, 'COLUMN_NAME');
    $added_columns = [];
    
    // Add breed column
    if (!in_array('breed', $existing_columns)) {
        $db->query("ALTER TABLE calves ADD COLUMN breed VARCHAR(50) NULL AFTER birth_weight");
        $added_columns[] = 'breed';
        echo "<div class='success'>✅ Added column: <strong>breed</strong></div>";
    } else {
        echo "<div class='info'>ℹ️ Column already exists: breed</div>";
    }
    
    // Add pen_location column
    if (!in_array('pen_location', $existing_columns)) {
        $db->query("ALTER TABLE calves ADD COLUMN pen_location VARCHAR(100) NULL AFTER breed");
        $added_columns[] = 'pen_location';
        echo "<div class='success'>✅ Added column: <strong>pen_location</strong></div>";
    } else {
        echo "<div class='info'>ℹ️ Column already exists: pen_location</div>";
    }
    
    // Create calf_weights table for growth tracking
    $weights_table_exists = $db->fetch("SHOW TABLES LIKE 'calf_weights'");
    
    if (!$weights_table_exists) {
        $db->query("
            CREATE TABLE `calf_weights` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `calf_id` int(11) NOT NULL,
              `weight_date` date NOT NULL,
              `weight` decimal(6,2) NOT NULL,
              `notes` text NULL,
              `recorded_by` int(11) NOT NULL,
              `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              FOREIGN KEY (`calf_id`) REFERENCES `calves`(`id`) ON DELETE CASCADE,
              FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`),
              INDEX `idx_calf_weight` (`calf_id`, `weight_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "<div class='success'>✅ Created table: <strong>calf_weights</strong> (for growth tracking)</div>";
    } else {
        echo "<div class='info'>ℹ️ Table already exists: calf_weights</div>";
    }
    
    echo "<div class='success'>
            <h3>🎉 Migration Completed Successfully!</h3>
            <p><strong>Database Changes:</strong></p>
            <ul style='margin: 0.5rem 0 0 1.5rem;'>
                <li>Added breed field to calves table</li>
                <li>Added pen_location field to calves table</li>
                <li>Created calf_weights table for weight tracking</li>
            </ul>
          </div>";
    
    echo "<div class='feature-list'>
            <h3>✨ New Features Available</h3>
            <ul>
                <li><strong>Enhanced Calf Passport:</strong> Beautiful timeline view of all calf events</li>
                <li><strong>Bulk Delete:</strong> Remove multiple test or unwanted calves at once</li>
                <li><strong>Bulk Batch Assignment:</strong> Move multiple calves to a batch quickly</li>
                <li><strong>Bulk Health Update:</strong> Update health status for multiple calves</li>
                <li><strong>Individual Delete:</strong> Delete single calves with confirmation</li>
                <li><strong>Enhanced Details:</strong> Track breed, pen location, and more</li>
                <li><strong>Sticky Header:</strong> Calf info stays visible while scrolling timeline</li>
                <li><strong>Print Passport:</strong> Print-friendly passport layout</li>
                <li><strong>Visual Timeline:</strong> Color-coded events with completion status</li>
            </ul>
          </div>";
    
    echo "<div class='warning'>
            <h3>📖 How to Use New Features</h3>
            <div class='step'>
                <strong>1. Bulk Actions (Calves Page):</strong>
                <ul style='margin: 0.5rem 0 0 1.5rem;'>
                    <li>Check boxes next to calves you want to manage</li>
                    <li>Use 'Select All' to select all visible calves</li>
                    <li>Choose action: Assign to Batch, Update Health, or Delete</li>
                </ul>
            </div>
            
            <div class='step'>
                <strong>2. Individual Delete:</strong>
                <ul style='margin: 0.5rem 0 0 1.5rem;'>
                    <li>Click the 🗑️ Delete button next to any calf</li>
                    <li>Confirm deletion when prompted</li>
                    <li>Calf will be marked as deleted (soft delete)</li>
                </ul>
            </div>
            
            <div class='step'>
                <strong>3. Enhanced Passport:</strong>
                <ul style='margin: 0.5rem 0 0 1.5rem;'>
                    <li>Click any Calf ID to view their passport</li>
                    <li>See all details in sticky header at top</li>
                    <li>Scroll through complete life events timeline</li>
                    <li>Print passport using 🖨️ Print button</li>
                </ul>
            </div>
          </div>";
    
    echo "<div style='margin-top: 2rem; text-align: center;'>
            <a href='/public/calves' class='btn'>🐮 Go to Calves Management</a>
            <a href='/public/' class='btn' style='background: #43A047;'>🏠 Dashboard</a>
          </div>";
          
    echo "<div style='margin-top: 2rem; padding: 1rem; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;'>
            <p><strong>⚠️ Note:</strong> You can safely delete this migration file after running it once.</p>
            <p style='margin: 0.5rem 0 0 0; font-size: 0.9rem;'>File location: <code>public/migrate_calf_passport.php</code></p>
          </div>";

} catch (Exception $e) {
    echo "<div class='error'>
            <h3>❌ Migration Failed</h3>
            <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p style='margin-top: 1rem;'>Please check your database permissions and try again.</p>
            <p style='font-size: 0.9rem; color: #666; margin-top: 1rem;'>
                <strong>Debug Info:</strong><br>
                File: " . htmlspecialchars($e->getFile()) . "<br>
                Line: " . $e->getLine() . "
            </p>
          </div>";
}

echo "</div></body></html>";
?>
</artifact>

### **7. Updated Route in public/index.php**

Add this route after the existing calves routes:
```php
case 'migrate-calf-passport':
    require_once BASE_PATH . '/public/migrate_calf_passport.php';
    break;