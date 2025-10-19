<?php
class MilkSetup {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function setupTable() {
        try {
            // Create milk allowances table
            $sql = "
            CREATE TABLE IF NOT EXISTS `milk_allowances` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `age_start` int(11) NOT NULL,
                `age_end` int(11) NOT NULL,
                `milk_amount` decimal(4,2) NOT NULL,
                `created_by` int(11) NOT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_age_range` (`age_start`, `age_end`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";

            $this->db->query($sql);

            // Check if table was created
            $tableExists = $this->db->fetch("SHOW TABLES LIKE 'milk_allowances'");
            if (empty($tableExists)) {
                throw new Exception("Failed to create milk_allowances table");
            }

            // Insert default milk allowances
            $defaultAllowances = [
                [0, 10, 2.0],
                [11, 15, 2.5],
                [16, 30, 3.0],
                [31, 60, 2.5]
            ];

            foreach ($defaultAllowances as $allowance) {
                $this->db->query(
                    "INSERT IGNORE INTO milk_allowances (age_start, age_end, milk_amount, created_by) VALUES (?, ?, ?, 1)",
                    $allowance
                );
            }

            return true;
        } catch (Exception $e) {
            error_log("Milk setup error: " . $e->getMessage());
            return false;
        }
    }

    public function checkTableExists() {
        try {
            $result = $this->db->fetch("SHOW TABLES LIKE 'milk_allowances'");
            return !empty($result);
        } catch (Exception $e) {
            return false;
        }
    }
}
?>